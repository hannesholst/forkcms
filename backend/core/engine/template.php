<?php

/*
 * This file is part of Fork CMS.
 *
 * For the full copyright and license information, please view the license
 * file that was distributed with this source code.
 */


/**
 * Template handling for the backend.
 *
 * @author <per@wijs.be>
 */
class BackendTemplate
{
	/**
	 * Should we add slashes to each value?
	 *
	 * @var bool
	 */
	private $addSlashes = false;

	/**
	 * @var \Twig_Environment
	 */
	private $twig;

	/**
	 * @var BackendURL
	 */
	private $url;

	/**
	 * @param BackendURL $url URL representing current request.
	 */
	public function __construct($url)
	{
		$this->url = $url;
		$this->twig = $this->getDefaultEnvironment();
		$this->registerFilters();
		$this->registerTags();
		$this->registerGlobals();
	}

	/**
	 * Assign to the template by name.
	 *
	 * @param string $name The name in the template by which the value shall be known.
	 * @param mixed $value The value to assign by name.
	 * @internal workaround for the fact that ->assign() is used in places where
	 *           the BackendTemplate was gotten from the reference and then assign()
	 *           is called on it.  We cannot control them all at once, so for the
	 *           time being we mimick this by adding a global template variable.
	 * @todo Track down occurences of this usage and remove where necessary.
	 */
	public function assign($name, $value)
	{
		$this->twig->addGlobal($name, $value);
	}

	/**
	 * @return \Twig_Environment The default environment.
	 */
	protected function getDefaultEnvironment()
	{
		$config = array(
			'cache' => BACKEND_CACHE_PATH . '/cached_templates',
			'charset' => SPOON_CHARSET,
			'debug' => SPOON_DEBUG,
		);
		$loader = new \Twig_Loader_Filesystem(BACKEND_CORE_PATH . '/layout/templates');
		// Add template directory for the current module, and namespace those
		// templates with the module's name.
		$loader->addPath(
			BACKEND_MODULES_PATH . '/' . $this->url->getModule() . '/layout/templates',
			$this->url->getModule()
		);

		return new \Twig_Environment($loader, $config);
	}

	/**
	 * Load a template.
	 * @param string $template The name/path of the template.
	 * @return \Twig_Template The loaded template.
	 */
	public function loadTemplate($template)
	{
		return $this->twig->loadTemplate($template);
	}

	/**
	 * Register filters with the template environment.
	 */
	private function registerFilters()
	{
		$this->twig->addFilter('addslashes',new Twig_Filter_Function('addslashes'));
		$this->twig->addFilter('ucfirst', new Twig_Filter_Function('SpoonFilter::ucfirst'));
		$this->twig->addFilter('geturl', new Twig_Filter_Function('BackendTemplateModifiers::getURL'));
		$this->twig->addFilter('getnavigation', new Twig_Filter_Function('BackendTemplateModifiers::getNavigation'));
		$this->twig->addFilter('getmainnavigation', new Twig_Filter_Function('BackendTemplateModifiers::getMainNavigation'));
		$this->twig->addFilter('rand', new Twig_Filter_Function('BackendTemplateModifiers::random'));
		$this->twig->addFilter('formatfloat', new Twig_Filter_Function('BackendTemplateModifiers::formatFloat'));
		$this->twig->addFilter('truncate', new Twig_Filter_Function('BackendTemplateModifiers::truncate'));
		$this->twig->addFilter('camelcase', new Twig_Filter_Function('SpoonFilter::toCamelCase'));
		$this->twig->addFilter('stripnewlines', new Twig_Filter_Function('BackendTemplateModifiers::stripNewlines'));
		$this->twig->addFilter('dump', new Twig_Filter_Function('BackendTemplateModifiers::dump'));
		$this->twig->addFilter('formatdate', new Twig_Filter_Function('BackendTemplateModifiers::formatDate'));
		$this->twig->addFilter('formattime', new Twig_Filter_Function('BackendTemplateModifiers::formatTime'));
		$this->twig->addFilter('formatdatetime', new Twig_Filter_Function('BackendTemplateModifiers::formatDateTime'));
		$this->twig->addFilter('formatnumber', new Twig_Filter_Function('BackendTemplateModifiers::formatNumber'));
		$this->twig->addFilter('tolabel', new Twig_Filter_Function('BackendTemplateModifiers::toLabel'));
	}

	/**
	 * Register global variables with the environment.
	 */
	private function registerGlobals()
	{
		$this->twig->addGlobal('CRLF', "\n");
		$this->twig->addGlobal('TAB', "\t");
		$this->twig->addGlobal('now', time());
		$this->twig->addGlobal('LANGUAGE', BL::getWorkingLanguage());
		$this->twig->addGlobal('SITE_MULTILANGUAGE', SITE_MULTILANGUAGE);
		$this->twig->addGlobal(
			'SITE_TITLE',
			BackendModel::getModuleSetting(
				'core',
				'site_title_' . BL::getWorkingLanguage(), SITE_DEFAULT_TITLE
			)
		);

		// TODO use SPOON_DEBUG again? That would be a SPOT violation, but you
		// _could_ consider it semantically more correct.
		$this->twig->addGlobal('debug', $this->twig->isDebug());

		// TODO hmz, here we assume the current user is authenticated already.
		$this->twig->addGlobal('user', BackendAuthentication::getUser());

		// TODO backend/core/engine/javascript.php
		//       It uses addSlashes, which was used to addslashes on the labels.
		// TODO Can we register BL as is? ie. as a class instead of an instance.
		$this->twig->addGlobal('BL', new BL());
	}

	private function registerTags()
	{
		$this->twig->addTokenParser(new FormTokenParser());
		$this->twig->addTokenParser(new EndformTokenParser());
		$this->twig->addTokenParser(new FormFieldTokenParser());
		$this->twig->addTokenParser(new FormFieldErrorTokenParser());
	}

	private function registerTranslations()
	{
		if(Spoon::exists('url')) $currentModule = Spoon::get('url')->getModule();
		elseif(isset($_GET['module']) && $_GET['module'] != '') $currentModule = (string) $_GET['module'];
		else $currentModule = 'core';

		$errors = BackendLanguage::getErrors();
		$realErrors = $errors['core'];

		$labels = BackendLanguage::getLabels();
		$realLabels = $labels['core'];

		$messages = BackendLanguage::getMessages();
		$realMessages = $messages['core'];

		// loop all errors, label, messages and add them again, but prefixed with Core. So we can decide in the
		// template to use the core-value instead of the one set by the module
		foreach($errors['core'] as $key => $value) $realErrors['Core' . $key] = $value;
		foreach($labels['core'] as $key => $value) $realLabels['Core' . $key] = $value;
		foreach($messages['core'] as $key => $value) $realMessages['Core' . $key] = $value;

		// are there errors for the current module?
		if(isset($errors[$currentModule]))
		{
			// loop the module-specific errors and reset them in the array with values we will use
			foreach($errors[$currentModule] as $key => $value) $realErrors[$key] = $value;
		}

		// are there labels for the current module?
		if(isset($labels[$currentModule]))
		{
			// loop the module-specific labels and reset them in the array with values we will use
			foreach($labels[$currentModule] as $key => $value) $realLabels[$key] = $value;
		}

		// are there messages for the current module?
		if(isset($messages[$currentModule]))
		{
			// loop the module-specific errors and reset them in the array with values we will use
			foreach($messages[$currentModule] as $key => $value) $realMessages[$key] = $value;
		}

		// execute addslashes on the values for the locale, will be used in JS
		if($this->addSlashes)
		{
			foreach($realErrors as &$value) $value = addslashes($value);
			foreach($realLabels as &$value) $value = addslashes($value);
			foreach($realMessages as &$value) $value = addslashes($value);
		}

		// sort the arrays (just to make it look beautifull)
		ksort($realErrors);
		ksort($realLabels);
		ksort($realMessages);

		$this->twig->addGlobal('err', $realErrors);
		$this->twig->addGlobal('lbl', $realLabels);
		$this->twig->addGlobal('msg', $realMessages);
	}

	/**
	 * Should we execute addSlashed on the locale?
	 *
	 * @param bool[optional] $on Enable addslashes.
	 */
	public function setAddSlashes($on = true)
	{
		$this->addSlashes = (bool) $on;
	}
}


/**
 * This is our class with custom modifiers.
 *
 * @author Tijs Verkoyen <tijs@sumocoders.be>
 */
class BackendTemplateModifiers
{
	/**
	 * Dumps the data
	 * 	syntax: {$var|dump}
	 *
	 * @param string $var The variable to dump.
	 * @return string
	 */
	public static function dump($var)
	{
		Spoon::dump($var, false);
	}

	/**
	 * Format a UNIX-timestamp as a date
	 * syntax: {$var|formatdate}
	 *
	 * @param int $var The UNIX-timestamp to format.
	 * @return string
	 */
	public static function formatDate($var)
	{
		// get setting
		$format = BackendAuthentication::getUser()->getSetting('date_format');

		// format the date
		return SpoonDate::getDate($format, (int) $var, BackendLanguage::getInterfaceLanguage());
	}

	/**
	 * Format a UNIX-timestamp as a datetime
	 * syntax: {$var|formatdatetime}
	 *
	 * @param int $var The UNIX-timestamp to format.
	 * @return string
	 */
	public static function formatDateTime($var)
	{
		// get setting
		$format = BackendAuthentication::getUser()->getSetting('datetime_format');

		// format the date
		return SpoonDate::getDate($format, (int) $var, BackendLanguage::getInterfaceLanguage());
	}

	/**
	 * Format a number as a float
	 * syntax: {$var|formatfloat}
	 *
	 * @param float $number The number to format.
	 * @param int[optional] $decimals The number of decimals.
	 * @return string
	 */
	public static function formatFloat($number, $decimals = 2)
	{
		$number = (float) $number;
		$decimals = (int) $decimals;

		// get setting
		$format = BackendAuthentication::getUser()->getSetting('number_format', 'dot_nothing');

		// get separators
		$separators = explode('_', $format);
		$separatorSymbols = array('comma' => ',', 'dot' => '.', 'space' => ' ', 'nothing' => '');
		$decimalSeparator = (isset($separators[0], $separatorSymbols[$separators[0]]) ? $separatorSymbols[$separators[0]] : null);
		$thousandsSeparator = (isset($separators[1], $separatorSymbols[$separators[1]]) ? $separatorSymbols[$separators[1]] : null);

		// format the number
		return number_format($number, $decimals, $decimalSeparator, $thousandsSeparator);
	}

	/**
	 * Format a number
	 * syntax: {$var|formatnumber}
	 *
	 * @param float $var The number to format.
	 * @return string
	 */
	public static function formatNumber($var)
	{
		$var = (float) $var;

		// get setting
		$format = BackendAuthentication::getUser()->getSetting('number_format', 'dot_nothing');

		// get amount of decimals
		$decimals = (strpos($var, '.') ? strlen(substr($var, strpos($var, '.') + 1)) : 0);

		// get separators
		$separators = explode('_', $format);
		$separatorSymbols = array('comma' => ',', 'dot' => '.', 'space' => ' ', 'nothing' => '');
		$decimalSeparator = (isset($separators[0], $separatorSymbols[$separators[0]]) ? $separatorSymbols[$separators[0]] : null);
		$thousandsSeparator = (isset($separators[1], $separatorSymbols[$separators[1]]) ? $separatorSymbols[$separators[1]] : null);

		// format the number
		return number_format($var, $decimals, $decimalSeparator, $thousandsSeparator);
	}

	/**
	 * Format a UNIX-timestamp as a date
	 * syntac: {$var|formatdate}
	 *
	 * @param int $var The UNIX-timestamp to format.
	 * @return string
	 */
	public static function formatTime($var)
	{
		// get setting
		$format = BackendAuthentication::getUser()->getSetting('time_format');

		// format the date
		return SpoonDate::getDate($format, (int) $var, BackendLanguage::getInterfaceLanguage());
	}

	/**
	 * Convert a var into main-navigation-html
	 * 	syntax: {$var|getmainnavigation}
	 *
	 * @param string[optional] $var A placeholder var, will be replaced with the generated HTML.
	 * @return string
	 */
	public static function getMainNavigation($var = null)
	{
		$var = (string) $var; // @todo what is this doing here?
		return Spoon::get('navigation')->getNavigation(1, 1);
	}

	/**
	 * Convert a var into navigation-html
	 * syntax: {$var|getnavigation:startdepth[:maximumdepth]}
	 *
	 * @param string[optional] $var A placeholder var, will be replaced with the generated HTML.
	 * @param int[optional] $startDepth The start depth of the navigation to get.
	 * @param int[optional] $endDepth The ending depth of the navigation to get.
	 * @return string
	 */
	public static function getNavigation($var = null, $startDepth = null, $endDepth = null)
	{
		$var = (string) $var;
		$startDepth = ($startDepth !== null) ? (int) $startDepth : 2;
		$endDepth = ($endDepth !== null) ? (int) $endDepth : null;

		// return navigation
		return Spoon::get('navigation')->getNavigation($startDepth, $endDepth);
	}

	/**
	 * Convert a var into a URL
	 * syntax: {$var|geturl:<action>[:<module>]}
	 *
	 * @param string[optional] $var A placeholder variable, it will be replaced with the URL.
	 * @param string[optional] $action The action to build the URL for.
	 * @param string[optional] $module The module to build the URL for.
	 * @param string[optional] $suffix A string to append.
	 * @return string
	 */
	public static function getURL($var = null, $action = null, $module = null, $suffix = null)
	{
		// redefine
		$var = (string) $var;
		$action = ($action !== null) ? (string) $action : null;
		$module = ($module !== null) ? (string) $module : null;

		// build the url
		return BackendModel::createURLForAction($action, $module, BackendLanguage::getWorkingLanguage()) . $suffix;
	}

	/**
	 * Get a random var between a min and max
	 * syntax: {$var|rand:min:max}
	 *
	 * @param string[optional] $var The string passed from the template.
	 * @param int $min The minimum number.
	 * @param int $max The maximim number.
	 * @return int
	 */
	public static function random($var = null, $min, $max)
	{
		$var = (string) $var;
		return rand((int) $min, (int) $max);
	}

	/**
	 * Convert a multiline string into a string without newlines so it can be handles by JS
	 * syntax: {$var|stripnewlines}
	 *
	 * @param string $var The variable that should be processed.
	 * @return string
	 */
	public static function stripNewlines($var)
	{
		return str_replace(array("\n", "\r"), '', $var);
	}

	/**
	 * Convert this string into a well formed label.
	 * 	syntax: {$var|tolabel}
	 *
	 * @param string $value The value to convert to a label.
	 * @return string
	 */
	public static function toLabel($value)
	{
		return SpoonFilter::ucfirst(BL::lbl(SpoonFilter::toCamelCase($value, '_', false)));
	}

	/**
	 * Truncate a string
	 * 	syntax: {$var|truncate:max-length[:append-hellip]}
	 *
	 * @param string[optional] $var A placeholder var, will be replaced with the generated HTML.
	 * @param int $length The maximum length of the truncated string.
	 * @param bool[optional] $useHellip Should a hellip be appended if the length exceeds the requested length?
	 * @return string
	 */
	public static function truncate($var = null, $length, $useHellip = true)
	{
		// remove special chars
		$var = htmlspecialchars_decode($var, ENT_QUOTES);

		// remove HTML
		$var = strip_tags($var);

		// less characters
		if(mb_strlen($var) <= $length) return SpoonFilter::htmlspecialchars($var);

		// more characters
		else
		{
			// hellip is seen as 1 char, so remove it from length
			if($useHellip) $length = $length - 1;

			// get the amount of requested characters
			$var = mb_substr($var, 0, $length);

			// add hellip
			if($useHellip) $var .= '…';

			return SpoonFilter::htmlspecialchars($var, ENT_QUOTES);
		}
	}
}


/**
 * Twig node for writing out a compiled version of a closing form tag.
 *
 * @author <per@wijs.be>
 */
class EndformNode extends Twig_Node
{
	/**
	 * @param int $lineno Line number in the template source file.
	 * @param string $tag
	 */
	public function __construct($lineno, $tag)
	{
		parent::__construct(array(), array(), $lineno, $tag);
	}

	/**
	 * @param Twig_Compiler $compiler
	 */
	public function compile(Twig_Compiler $compiler)
	{
		$compiler
			->addDebugInfo($this)
			->write('echo \'</form>\';');
	}
}


/**
 * Twig token parser for form closing tag.
 *
 * @author <per@wijs.be>
 */
class EndformTokenParser extends Twig_TokenParser
{
	/**
	 * @param Twig_Token $token Token consumed by the lexer.
	 * @return Twig_Node
	 * @throw Twig_Error_Syntax
	 */
	public function parse(Twig_Token $token)
	{
		$stream = $this->parser->getStream();
		if($stream->getCurrent()->getType() != Twig_Token::BLOCK_END_TYPE)
		{
			$error = sprintf("'%s' does not require any arguments.", $this->getTag());
			throw new Twig_Error_Syntax($error, $token->getLine(), $this->parser->getFilename());
		}
		$stream->expect(Twig_Token::BLOCK_END_TYPE);

		if(FormState::$current === null)
		{
			throw new Twig_Error_Syntax(
				'Trying to close a form tag, while none opened',
				$token->getLine(),
				$this->parser->getFilename()
			);
		}
		else
		{
			FormState::$current = null;
		}
		return new EndformNode($token->getLine(), $this->getTag());
	}

	/**
	 * @return string
	 */
	public function getTag()
	{
		return 'endform';
	}
}


/**
 * Twig node for writing out the compiled version of form field.
 *
 * @author <per@wijs.be>
 */
class FormFieldNode extends Twig_Node
{
	private $form;
	private $field;

	/**
	 * @param string $form Name of the template var holding the form this field
	 *                     belongs to.
	 * @param string $field Name of the field to render.
	 * @param int $lineno Line number in the template source file.
	 * @param string $tag
	 */
	public function __construct($form, $field, $lineno, $tag)
	{
		parent::__construct(array(), array(), $lineno, $tag);
		$this->form = $form;
		$this->field = $field;
	}

	/**
	 * @param Twig_Compiler $compiler
	 */
	public function compile(Twig_Compiler $compiler)
	{
		$parseField = "\$context['{$this->form}']->getField('{$this->field}')->parse()";
		$compiler
			->addDebugInfo($this)
			->write("echo $parseField;\n")
		;
	}
}


/**
 * Twig token parser for form fields.
 *
 * @author <per@wijs.be>
 */
class FormFieldTokenParser extends Twig_TokenParser
{
	/**
	 * @param Twig_Token $token consumed token by the lexer.
	 * @return Twig_Node
	 * @throw Twig_Error_Syntax
	 */
	public function parse(Twig_Token $token)
	{
		$stream = $this->parser->getStream();
		$field = $stream->expect(Twig_Token::NAME_TYPE)->getValue();
		$stream->expect(Twig_Token::BLOCK_END_TYPE);
		if(FormState::$current === null)
		{
			throw new Twig_Error_Syntax(
				sprintf('Cannot render form field [%s] outside a form element', $field),
				$token->getLine(),
				$this->parser->getFilename()
			);
		}
		return new FormFieldNode(FormState::$current, $field, $token->getLine(), $this->getTag());
	}

	/**
	 * @return string
	 */
	public function getTag()
	{
		return 'form_field';
	}
}


/**
 * Twig note for writing out the compiled version of a form field error.
 *
 * @author <per@wijs.be>
 */
class FormFieldErrorNode extends Twig_Node
{
	private $form;
	private $field;

	/**
	 * @param string $form Name of the template var holding the form this field
	 *                     error belongs to.
	 * @param string $field Name of the field of which we need to render the error.
	 * @param int $lineno Line number in the template source file.
	 * @param string $tag the name of the template tag.
	 */
	public function __construct($form, $field, $lineno, $tag)
	{
		parent::__construct(array(), array(), $lineno, $tag);
		$this->form = $form;
		$this->field = $field;
	}

	public function compile(Twig_Compiler $compiler)
	{
		$writeErrorMessage = "echo "
			. "\$context['{$this->form}']->getField('{$this->field}')->getErrors() "
			. "? '<span class=\"formError\">' "
			. ". \$context['{$this->form}']->getField('{$this->field}')->getErrors() "
			. ". '</span>' : '';";
		$compiler
			->addDebugInfo($this)
			->write($writeErrorMessage)
		;
	}
}

/**
 * Twig token parser for form field errors.
 *
 * @author <per@wijs.be>
 */
class FormFieldErrorTokenParser extends Twig_TokenParser
{
	/**
	 * @param Twig_Token $token consumed token by the lexer.
	 * @return Twig_Node
	 * @throw Twig_Error_Syntax
	 */
	public function parse(Twig_Token $token)
	{
		$stream = $this->parser->getStream();
		$field = $stream->expect(Twig_Token::NAME_TYPE)->getValue();
		$stream->expect(Twig_Token::BLOCK_END_TYPE);
		if(FormState::$current === null)
		{
			throw new Twig_Error_Syntax(
				sprintf('Cannot render form field error [%s] outside a form element', $field),
				$token->getLine(),
				$this->parser->getFilename()
			);
		}
		return new FormFieldErrorNode(
			FormState::$current, $field, $token->getLine(), $this->getTag());
	}

	/**
	 * @return string
	 */
	public function getTag()
	{
		return 'form_field_error';
	}
}


/**
 * Keeps the state of a form between opening and closing the tag.
 * Since forms cannot be nested, we can resort to a quick 'n dirty yes/no global state.
 *
 * In the future we could remove it and use stack {push,popp}ing, but I'm hoping
 * we're using symfony forms or some other more OO form library.
 *
 * @author <per@wijs.be>
 */
class FormState
{
	public static $current = null;
}


/**
 * Twig node for writing out the compiled representation of an opeing form tag.
 *
 * @author <per@wijs.be>
 */
class FormNode extends Twig_Node
{
	/**
	 * @var string Template variable holding the form.
	 */
	private $form;

	/**
	 * @param string $form The name of the template variable to which the form is assigned
	 * @param int $lineno
	 * @param string $tag
	 */
	public function __construct($form, $lineno, $tag)
	{
		parent::__construct(array(), array(), $lineno, $tag);
		$this->form = $form;
	}

	/**
	 * @param Twig_Compiler $compiler
	 */
	public function compile(Twig_Compiler $compiler)
	{
		// Set some string representations to make the code writing via the
		// compiler a bit more readable. ("a bit")
		$frm = "\$context['{$this->form}']";
		$frmAction = $frm . '->getAction()';
		$frmMethod = $frm . '->getMethod()';
		$frmName = $frm . '->getName()';
		$frmToken = $frm . '->getToken()';
		$frmUseToken = $frm . '->getUseToken()';
		$frmParamsHtml = $frm . '->getParametersHTML()';
		$frmAttrAction = ' action="\', ' . $frmAction . ', \'"';
		$frmAttrMethod = ' method="\', ' . $frmMethod . ', \'"';
		$hiddenFormName = '<input type="hidden" name="form" value="\', ' . $frmName . ', \'" id="form\', ucfirst(' . $frmName . '), \'" />';
		$hiddenFormToken = '<input type="hidden" name="form_token" value="\', ' . $frmToken . ', \'" id="formToken\', ucfirst(' . $frmName . '), \'" />';

		// oh boy
		$htmlAcceptCharset = (SPOON_CHARSET == 'utf-8')
			? ' accept-charset="UTF-8"'
			: '';

		$compiler
			->addDebugInfo($this)

			->write('echo \'<form')
			->raw($frmAttrMethod)
			->raw($frmAttrAction)
			->raw($htmlAcceptCharset)
			->raw("', ")
			->raw(' ' . $frmParamsHtml)
			->raw(', \'')
			->raw('>\'')
			->raw(";\n")

			->write("echo '$hiddenFormName';\n")
			->write("if($frmUseToken) echo '$hiddenFormToken';")
		;
	}
}


/**
 * Twig template tag for the start/opening element of a form tag.
 *
 * @author <per@wijs.be>
 */
class FormTokenParser extends Twig_TokenParser
{
	/**
	 * @param Twig_Token $token
	 * @return Twig_Node
	 * @throw Twig_Error_Syntax
	 */
	public function parse(Twig_Token $token)
	{
		$stream = $this->parser->getStream();
		$form = $stream->expect(Twig_Token::NAME_TYPE)->getValue();
		$stream->expect(Twig_Token::BLOCK_END_TYPE);

		if(FormState::$current !== null)
		{
			throw new Twig_Error_Syntax(
				sprintf(
					'form [%s] not closed while opening form [%s]',
					FormState::$current,
					$form
				),
				$token->getLine(),
				$stream->getFilename()
			);
		}
		else
		{
			FormState::$current = $form;
		}

		return new FormNode($form, $token->getLine(), $this->getTag());
	}

	/**
	 * @return string
	 */
	public function getTag()
	{
		return 'form';
	}
}









// {{{ OLD NON USED
/**
 * This is our extended version of SpoonTemplate
 * This class will handle a lot of stuff for you, for example:
 * 	- it will assign all labels
 * 	- it will map some modifiers
 * 	- it will assign a lot of constants
 * 	- ...
 *
 * @author Davy Hellemans <davy.hellemans@netlash.com>
 * @author Tijs Verkoyen <tijs@sumocoders.be>
 */
class BackendTemplate_OLD_ extends SpoonTemplate
{
	/**
	 * Should we add slashes to each value?
	 *
	 * @var bool
	 */
	private $addSlashes = false;

	/**
	 * URL instance
	 *
	 * @var	BackendURL
	 */
	private $URL;

	/**
	 * The constructor will store the instance in the reference, preset some settings and map the custom modifiers.
	 *
	 * @param bool[optional] $addToReference Should the instance be added into the reference.
	 */
	public function __construct($addToReference = true)
	{
		parent::__construct();

		// get URL instance
		if(Spoon::exists('url')) $this->URL = Spoon::get('url');

		// store in reference so we can access it from everywhere
		if($addToReference) Spoon::set('template', $this);

		// set cache directory
		$this->setCacheDirectory(BACKEND_CACHE_PATH . '/cached_templates');

		// set compile directory
		$this->setCompileDirectory(BACKEND_CACHE_PATH . '/compiled_templates');

		// when debugging, the template should be recompiled every time
		$this->setForceCompile(SPOON_DEBUG);

		// map custom modifiers
		$this->mapCustomModifiers();

		// parse authentication levels
		$this->parseAuthentication();
	}

	/**
	 * Output the template into the browser
	 * Will also assign the interfacelabels and all user-defined constants.
	 *
	 * @param string $template The path for the template.
	 * @param bool[optional] $customHeaders Are there custom headers set?
	 */
	public function display($template, $customHeaders = false)
	{
		$this->parseConstants();
		$this->parseAuthenticatedUser();
		$this->parseDebug();
		$this->parseLabels();
		$this->parseLocale();
		$this->parseVars();

		// parse headers
		if(!$customHeaders)
		{
			SpoonHTTP::setHeaders('Content-type: text/html;charset=' . SPOON_CHARSET);
		}

		parent::display($template);
	}

	/**
	 * Map the fork-specific modifiers
	 */
	private function mapCustomModifiers()
	{
		// convert var into an URL, syntax {$var|geturl:<pageId>}
		$this->mapModifier('geturl', array('BackendTemplateModifiers', 'getURL'));

		// convert var into navigation, syntax {$var|getnavigation:<startdepth>:<enddepth>}
		$this->mapModifier('getnavigation', array('BackendTemplateModifiers', 'getNavigation'));

		// convert var into navigation, syntax {$var|getmainnavigation}
		$this->mapModifier('getmainnavigation', array('BackendTemplateModifiers', 'getMainNavigation'));

		// rand
		$this->mapModifier('rand', array('BackendTemplateModifiers', 'random'));

		// string
		$this->mapModifier('formatfloat', array('BackendTemplateModifiers', 'formatFloat'));
		$this->mapModifier('truncate', array('BackendTemplateModifiers', 'truncate'));
		$this->mapModifier('camelcase', array('SpoonFilter', 'toCamelCase'));
		$this->mapModifier('stripnewlines', array('BackendTemplateModifiers', 'stripNewlines'));

		// debug stuff
		$this->mapModifier('dump', array('BackendTemplateModifiers', 'dump'));

		// dates
		$this->mapModifier('formatdate', array('BackendTemplateModifiers', 'formatDate'));
		$this->mapModifier('formattime', array('BackendTemplateModifiers', 'formatTime'));
		$this->mapModifier('formatdatetime', array('BackendTemplateModifiers', 'formatDateTime'));

		// numbers
		$this->mapModifier('formatnumber', array('BackendTemplateModifiers', 'formatNumber'));

		// label (locale)
		$this->mapModifier('tolabel', array('BackendTemplateModifiers', 'toLabel'));
	}

	/**
	 * Parse the settings for the authenticated user
	 */
	private function parseAuthenticatedUser()
	{
		// check if the current user is authenticated
		if(BackendAuthentication::getUser()->isAuthenticated())
		{
			// show stuff that only should be visible if authenticated
			$this->assign('isAuthenticated', true);

			// get authenticated user-settings
			$settings = (array) BackendAuthentication::getUser()->getSettings();

			foreach($settings as $key => $setting)
			{
				// redefine setting
				$setting = ($setting === null) ? '' : $setting;

				// assign setting
				$this->assign('authenticatedUser' . SpoonFilter::toCamelCase($key), $setting);
			}

			// check if this action is allowed
			if(BackendAuthentication::isAllowedAction('edit', 'users'))
			{
				// assign special vars
				$this->assign(
					'authenticatedUserEditUrl',
					BackendModel::createURLForAction(
						'edit',
						'users',
						null,
						array('id' => BackendAuthentication::getUser()->getUserId())
					)
				);
			}
		}
	}

	/**
	 * Parse the authentication settings for the authenticated user
	 */
	private function parseAuthentication()
	{
		// init var
		$db = BackendModel::getDB();

		// get allowed actions
		$allowedActions = (array) $db->getRecords(
			'SELECT gra.module, gra.action, MAX(gra.level) AS level
			 FROM users_sessions AS us
			 INNER JOIN users AS u ON us.user_id = u.id
			 INNER JOIN users_groups AS ug ON u.id = ug.user_id
			 INNER JOIN groups_rights_actions AS gra ON ug.group_id = gra.group_id
			 WHERE us.session_id = ? AND us.secret_key = ?
			 GROUP BY gra.module, gra.action',
			array(SpoonSession::getSessionId(), SpoonSession::get('backend_secret_key'))
		);

		// loop actions and assign to template
		foreach($allowedActions as $action)
		{
			if($action['level'] == '7') $this->assign('show' . SpoonFilter::toCamelCase($action['module'], '_') . SpoonFilter::toCamelCase($action['action'], '_'), true);
		}
	}

	/**
	 * Parse all user-defined constants
	 */
	private function parseConstants()
	{
		// constants that should be protected from usage in the template
		$notPublicConstants = array('DB_TYPE', 'DB_DATABASE', 'DB_HOSTNAME', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD');

		// get all defined constants
		$constants = get_defined_constants(true);

		// init var
		$realConstants = array();

		// remove protected constants aka constants that should not be used in the template
		foreach($constants['user'] as $key => $value)
		{
			if(!in_array($key, $notPublicConstants)) $realConstants[$key] = $value;
		}

		// we should only assign constants if there are constants to assign
		if(!empty($realConstants)) $this->assign($realConstants);

		// we use some abbrviations and common terms, these should also be assigned
		$this->assign('LANGUAGE', BackendLanguage::getWorkingLanguage());

		if($this->URL instanceof BackendURL)
		{
			// assign the current module
			$this->assign('MODULE', $this->URL->getModule());

			// assign the current action
			if($this->URL->getAction() != '') $this->assign('ACTION', $this->URL->getAction());
		}

		// is the user object filled?
		if(BackendAuthentication::getUser()->isAuthenticated())
		{
			// assign the authenticated users secret key
			$this->assign('SECRET_KEY', BackendAuthentication::getUser()->getSecretKey());

			// assign the authentiated users preferred interface language
			$this->assign('INTERFACE_LANGUAGE', (string) BackendAuthentication::getUser()->getSetting('interface_language'));
		}

		// assign some variable constants (such as site-title)
		$this->assign('SITE_TITLE', BackendModel::getModuleSetting('core', 'site_title_' . BackendLanguage::getWorkingLanguage(), SITE_DEFAULT_TITLE));
	}

	/**
	 * Assigns an option if we are in debug-mode
	 */
	private function parseDebug()
	{
		$this->assign('debug', SPOON_DEBUG);
	}

	/**
	 * Assign the labels
	 */
	private function parseLabels()
	{
		// grab the current module
		if(Spoon::exists('url')) $currentModule = Spoon::get('url')->getModule();
		elseif(isset($_GET['module']) && $_GET['module'] != '') $currentModule = (string) $_GET['module'];
		else $currentModule = 'core';

		// init vars
		$realErrors = array();
		$realLabels = array();
		$realMessages = array();

		// get all errors
		$errors = BackendLanguage::getErrors();

		// get all labels
		$labels = BackendLanguage::getLabels();

		// get all messages
		$messages = BackendLanguage::getMessages();

		// set the begin state
		$realErrors = $errors['core'];
		$realLabels = $labels['core'];
		$realMessages = $messages['core'];

		// loop all errors, label, messages and add them again, but prefixed with Core. So we can decide in the
		// template to use the core-value instead of the one set by the module
		foreach($errors['core'] as $key => $value) $realErrors['Core' . $key] = $value;
		foreach($labels['core'] as $key => $value) $realLabels['Core' . $key] = $value;
		foreach($messages['core'] as $key => $value) $realMessages['Core' . $key] = $value;

		// are there errors for the current module?
		if(isset($errors[$currentModule]))
		{
			// loop the module-specific errors and reset them in the array with values we will use
			foreach($errors[$currentModule] as $key => $value) $realErrors[$key] = $value;
		}

		// are there labels for the current module?
		if(isset($labels[$currentModule]))
		{
			// loop the module-specific labels and reset them in the array with values we will use
			foreach($labels[$currentModule] as $key => $value) $realLabels[$key] = $value;
		}

		// are there messages for the current module?
		if(isset($messages[$currentModule]))
		{
			// loop the module-specific errors and reset them in the array with values we will use
			foreach($messages[$currentModule] as $key => $value) $realMessages[$key] = $value;
		}

		// execute addslashes on the values for the locale, will be used in JS
		if($this->addSlashes)
		{
			foreach($realErrors as &$value) $value = addslashes($value);
			foreach($realLabels as &$value) $value = addslashes($value);
			foreach($realMessages as &$value) $value = addslashes($value);
		}

		// sort the arrays (just to make it look beautifull)
		ksort($realErrors);
		ksort($realLabels);
		ksort($realMessages);

		// assign errors
		$this->assignArray($realErrors, 'err');

		// assign labels
		$this->assignArray($realLabels, 'lbl');

		// assign messages
		$this->assignArray($realMessages, 'msg');
	}

	/**
	 * Parse the locale (things like months, days, ...)
	 */
	private function parseLocale()
	{
		// init vars
		$localeToAssign = array();

		// get months
		$monthsLong = SpoonLocale::getMonths(BackendLanguage::getInterfaceLanguage(), false);
		$monthsShort = SpoonLocale::getMonths(BackendLanguage::getInterfaceLanguage(), true);

		// get days
		$daysLong = SpoonLocale::getWeekDays(BackendLanguage::getInterfaceLanguage(), false, 'sunday');
		$daysShort = SpoonLocale::getWeekDays(BackendLanguage::getInterfaceLanguage(), true, 'sunday');

		// build labels
		foreach($monthsLong as $key => $value) $localeToAssign['locMonthLong' . SpoonFilter::ucfirst($key)] = $value;
		foreach($monthsShort as $key => $value) $localeToAssign['locMonthShort' . SpoonFilter::ucfirst($key)] = $value;
		foreach($daysLong as $key => $value) $localeToAssign['locDayLong' . SpoonFilter::ucfirst($key)] = $value;
		foreach($daysShort as $key => $value) $localeToAssign['locDayShort' . SpoonFilter::ucfirst($key)] = $value;

		// assign
		$this->assignArray($localeToAssign);
	}

	/**
	 * Parse some vars
	 */
	private function parseVars()
	{
		// assign a placeholder var
		$this->assign('var', '');

		// assign current timestamp
		$this->assign('timestamp', time());

		// assign body ID
		if($this->URL instanceof BackendURL)
		{
			$this->assign('bodyID', SpoonFilter::toCamelCase($this->URL->getModule(), '_', true));

			// build classes
			$bodyClass = SpoonFilter::toCamelCase($this->URL->getModule() . '_' . $this->URL->getAction(), '_', true);

			// special occasions
			if($this->URL->getAction() == 'add' || $this->URL->getAction() == 'edit') $bodyClass = $this->URL->getModule() . 'AddEdit';

			// assign
			$this->assign('bodyClass', $bodyClass);
		}
	}

	/**
	 * Should we execute addSlashed on the locale?
	 *
	 * @param bool[optional] $on Enable addslashes.
	 */
	public function setAddSlashes($on = true)
	{
		$this->addSlashes = (bool) $on;
	}
}



// }}}
//
