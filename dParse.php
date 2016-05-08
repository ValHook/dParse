<?php
/**
 * dParse is a free powerful HTML DOM parser written in PHP.
 * It supports querying with CSS selectors like jQuery and has a lot of features to perform text formatting, website querying
 * and DOM nodes analysing.
 *
 * @version		1.0
 * @author		Valentin Mercier
 * @link		http://valentinmercier.fr
 * @package		dParse
 * @require		PHP 5+, cURL 
 * @license     MIT License

	The MIT License (MIT)

	Copyright (c) 2014 Valentin Mercier <vmercier@me.com>

	Permission is hereby granted, free of charge, to any person obtaining a copy
	of this software and associated documentation files (the "Software"), to deal
	in the Software without restriction, including without limitation the rights
	to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
	copies of the Software, and to permit persons to whom the Software is
	furnished to do so, subject to the following conditions:

	The above copyright notice and this permission notice shall be included in
	all copies or substantial portions of the Software.

	THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
	IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
	FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
	AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
	LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
	OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
	THE SOFTWARE.
 */

/* Perform system verifactions before doing anything else */
if (version_compare(PHP_VERSION, '5.0.0', '<'))
	die("DPARSE ERROR: dParse requires at least PHP version 5.0.0.");
if (!function_exists('curl_version'))
	die("DPARSE ERROR: dParse requires cURL.");



/* Few defines, for internal use */
define('DPARSE', true);
define('DPARSE_OPENING_TAG', 0);
define('DPARSE_CLOSING_TAG', 1);
define('DPARSE_SELF_CLOSING_TAG', 2);



/* Beginner function that downloads source from a target without building a DOM
 * @param    $source: It can be either a web URL, a local filename or a plain html string 
 * @param    $args: All possible arguments are listed within the function declaration */
function dParseGetContents($source, $args = array())
{
	return createdParseDOM($source, $args, FALSE);
}


/* Beginner function that creates the dParse main DOM object
 * @param    $source: It can be either a web URL, a local filename or a plain html string 
 * @param    $args: All possible arguments are listed within the function declaration */
function createdParseDOM($source, array $args = array(), $createDOM = TRUE)
{
	/* If no source is provided then we stop here */
	if (!$source)
		die("DPARSE ERROR: An empty document was provided.");

	/* Begin timer */
	$timer = microtime(true);

	/* Handle default arguments */
	$defaultargs = array("method" => "GET",
							"fake_user_agent" => NULL,
							"fake_http_referer" => NULL,
							"force_input_charset" => NULL,
							"output_charset" => NULL,
							"strip_whitespaces" => false,
							"connect_timeout" => 10,
							"transfer_timeout" => 40,
							"verify_peer" => false,
							"http_auth_username" => NULL,
							"http_auth_password" => NULL,
							"cookie_file" => NULL,
							"is_xml" => FALSE,
							"enable_logger" => FALSE);

	foreach ($defaultargs as $key => $value)
		if (!isset($args[$key]))
			$args[$key] = $value;



	/* Determine the kind of input source */
	/* Web URL Case (the  '://' suffix is used to differenciate it from a possible file whose name could start by 'http' */
	if (strpos($source, "http://") === 0 || strpos($source, "https://") === 0) {

		/* Check the HTTP method specified */
		if (strtoupper($args['method']) != "GET" && strtoupper($args['method']) != "POST")
			die("Invalid HTTP method specified");

		/* We retrieve the HTML using cURL */
		$ch = curl_init();
		$url = $source;
		$urlparametersoffset = strpos($source, "?");
		if ($args['method'] == "POST" && $urlparametersoffset) {

			/* HTTP POST case */
			curl_setopt( $ch, CURLOPT_URL, substr($source, 0, $urlparametersoffset) );
			curl_setopt( $ch, CURLOPT_POST, true);
			curl_setopt( $ch, CURLOPT_POSTFIELDS, substr($source, $urlparametersoffset + 1) );

		} else {

			/* HTTP GET case */
			curl_setopt( $ch, CURLOPT_URL, $source );

		}

		/* Set other cURL parameters */
  		curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
  		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  		curl_setopt($ch, CURLOPT_AUTOREFERER, true);
  		curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
  		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $args['verify_peer']);
  		curl_setopt($ch,CURLOPT_CONNECTTIMEOUT, $args['connect_timeout']);
  		curl_setopt($ch,CURLOPT_TIMEOUT, $args['transfer_timeout']);
  		if ($args['fake_user_agent'])
			curl_setopt($ch, CURLOPT_USERAGENT, $args['fake_user_agent']);
		if ($args['fake_http_referer'])
			curl_setopt($ch, CURLOPT_REFERER, $args['fake_http_referer']);
		if ($args['cookie_file']) {
			curl_setopt($ch, CURLOPT_COOKIEJAR, $args['cookie_file']);
			curl_setopt($ch, CURLOPT_COOKIEFILE, $args['cookie_file']);
		}
		if ($args['http_auth_username'] && $args['http_auth_password']) {
			curl_setopt($ch, CURLOPT_USERPWD, $args['http_auth_username'] . ":" . $args['http_auth_password']);  
			curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
			curl_setopt($ch, CURLOPT_UNRESTRICTED_AUTH, true);
		}

		/* Download the page */
		$source = curl_exec($ch);
		$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
		/* Error handler */
		if (curl_error($ch))
			die(curl_error($ch));
		/* Properly close cURL */
		curl_close($ch);
	}

	/* Filepath case */
	else if (file_exists($source)) {

		/* We just use file_get_contents() to get the data, it is the fastest way to achieve this */
		$filename = $source;
		$source = file_get_contents($source);

	}

	/* Plain HTML string case */
	else {

		/* Well the $source variable already has the raw content*/
		void;

	}

	/* Build the DOM */
	if ($createDOM)
		return new DParseDOM($source, $timer, $content_type, $args['force_input_charset'], $args["output_charset"], $args["strip_whitespaces"], $args['is_xml'], $args['enable_logger']);
	else
		return $source;
}





/** Debug Class */
class DParseLogger
{
	
	/* Log array */
	private $logs;
	private $enabled;
	
	/* Log methods */
	function __construct() { $this->logs = array(); }
	function log($message) { if ($this->enabled) $this->logs[] = $message; }
	function getLogs() { return $this->logs; }
	function getLastLog() { return $this->logs[count($this->logs) - 1]; }
	function saveLogs($filename) { file_put_contents($filename, $this->logs); }
	function showLogs() { print_r($this->logs); }
	function clear() { unset($this->logs); $this->logs = array(); }
	function enable($bool) { $this->enabled = $bool; }
	function isEnabled() { return $this->enabled; }

}





/** dParse DOM Class **/
class DParseDOM
{

	/* Variables */
	private $n_nodes;
	private $n_invalid_tags;
	public $nodes = array();
	private $root;
	private $noisy_tags = array();
	private $ignored_tags = array();
	private $self_closing_tags = array('img'=>1, 'br'=>1, 'input'=>1, 'meta'=>1, 'link'=>1, 'hr'=>1, 'base'=>1, 'embed'=>1, 'spacer'=>1, 'link' =>1);
	private $whitespaces = array(" " => 1, "\t" => 1, "\r" => 1, "\n" => 1);
	private $cursor;
	private $input_charset;
	private $output_charset;
	private $strip_whitespaces;
	private $is_xml;
	private $logger;


	/* MEMORY METHODS */
	/* Constructor */
	function __construct($content, $timer, $content_type, $force_input_charset, $output_charset, $strip_whitespaces, $is_xml, $enable_logger)
	{

		/* Initiate the logger */
		$this->logger = new DParseLogger();
		$logger = $this->logger;
		$logger->enable($enable_logger);

		/* Save download/read time and reset timer */
		$timer = microtime(true) - $timer;
		$logger->log("Retrieved document contents in " . $timer . " seconds");
		$timer = microtime(true);

		/* Primary settings */
		$logger->log("Now parsing document ...");
		$this->content = $content;
		$this->output_charset = $output_charset;
		$this->strip_whitespaces = $strip_whitespaces;
		$this->is_xml = $is_xml;

		/* Get the charset from the headers */
		if (!$force_input_charset) {
			$success = preg_match('/charset=(.+)/', $content_type, $matches);
			if ($success) {
				$matches[1] = strtoupper($matches[1]);
				$this->input_charset = $matches[1];
				$logger->log("The document charset was found and is: " . $matches[1]);
			}
			else
				$logger->log("The document charset was not found inside the headers, a new attemp to find it within the meta tags will be made later");

		} else {
			$logger->log("Input charset forced as: ".$force_input_charset);
			$this->input_charset = $force_input_charset;
		}


		/* Locate useless tags, so that we can ignore them afterwards */
		$noise = array("'<!--(.*?)-->'is",
						"'<!DOCTYPE(.*?)>'is",
						"'<!\[CDATA\[(.*?)\]\]>'is",
						//"'<\s*script[^>]*[^/]>(.*?)<\s*/\s*script\s*>'is",
						//"'<\s*script\s*>(.*?)<\s*/\s*script\s*>'is",
						//"'<\s*style[^>]*[^/]>(.*?)<\s*/\s*style\s*>'is",
						//"'<\s*style\s*>(.*?)<\s*/\s*style\s*>'is",
						//"'<\s*(?:code)[^>]*>(.*?)<\s*/\s*(?:code)\s*>'is",
						"'(<\?)(.*?)(\?>)'s",
						"'(\{\w)(.*?)(\})'s"
						);
		foreach ($noise as $n)
			$this->locate_noise($n);


		/* And finally parse the whole document to build the DOM */
		$this->parse();

		/* And make a new attempt to determine the charset using the meta tags now parsable */
		if (!$this->input_charset) {
			$charset = $this->find("meta[charset]", -1, FALSE);
			if ($charset) $charset = $charset->eq(0)->charset;
			if ($charset) {
				$this->input_charset = $charset;
				$logger->log("Found a charset inside the meta tags: $this->input_charset");
			}
		}

		/* Finally log the results counts */
		$logger->log("Found " . count($this->noisy_tags) . " noisy tag" . (count($this->noisy_tags) == 1 ? "" : "s") . ".");
		$logger->log("Ignored " . count($this->ignored_tags) . " tag" . (count($this->ignored_tags) == 1 ? "" : "s") . ".");
		$type = $this->is_xml ? "XML" : "HTML";
		$logger->log("Found " . $this->n_nodes . " element" . ($this->n_nodes == 1 ? "" : "s") . ", including " . $this->n_invalid_tags . " recreated tag" . ($this->n_invalid_tags == 1 ? "":"s") . " to fix invalid " . $type);
		$timer = microtime(true) - $timer;
		$logger->log("Document parsed in " . $timer . " seconds");
		$logger->log("Memory peak usage: " . number_format(memory_get_peak_usage(), 0, ".", " ") . " bytes");
	}




	/* PARSING METHODS */
	/* Locate useless tags, so that we can ignore them afterwards */
	protected function locate_noise($pattern)
	{
		/* Find noisy tags using regular expressions */
        $count = preg_match_all($pattern, $this->content, $matches, PREG_SET_ORDER|PREG_OFFSET_CAPTURE);

        for ($i=0; $i < $count; $i++)
        {
        	/* Write down their location to ignore them later */
        	$this->noisy_tags[] = array("beginpos" => $matches[$i][0][1] , "length" => strlen($matches[$i][0][0]));
        }
	}

	protected function is_noisy()
	{
		foreach($this->noisy_tags as $n)
			if ($this->cursor >= $n["beginpos"] && $this->cursor <= $n["beginpos"] + $n["length"])
				return true;
		return false;
	}



	/* Main parser */
	protected function parse()
	{
		$this->cursor = 0;
		$this->n_nodes = 0;
		$this->n_invalid_tags;
		$breadcrumb = array();
		$tagstoadd = array();
		$depth = 0;
		$parent = NULL;
		$prev = NULL;
		$parents = array();
		$prevs = array();


		/* Browse through all the tags */
		while ($nexttag = $this->findNextTag()) {

			/* Fix invalid HTML if needed */
			/* Ignore closing tag if the document does not start with an opening tag */
			if ($depth == 0 && $nexttag['nature'] == DPARSE_CLOSING_TAG) {
				$this->ignored_tags[] = $nexttag;
				continue;
			}


			/* Fix omitted and invalid closing tags */
			if ($nexttag['nature'] == DPARSE_CLOSING_TAG) {
				
				/* OMMITTED TAGS */
				/* First browse the breadcrumb backwards to find the corresponding opening tag */
				$tmpbreadcrumb = $breadcrumb; // Save the original breadcrumb
				$tmp_n_invalid_tags = $this->n_invalid_tags; // Save the original count of invalid tags
				
				do {
					$prevtag = array_pop($tmpbreadcrumb); // Will never be empty at this point
					$prevtag['nature'] = DPARSE_CLOSING_TAG;
					unset($prevtag['attr']);
					array_unshift($tagstoadd, $prevtag);
					$tmp_n_invalid_tags ++;
				} while ($prevtag['tagname'] != $nexttag['tagname'] && !empty($tmpbreadcrumb));
				
				/* Then remove the first element that is not an omitted closing tag, but the opening tag of the current closing tag */
				array_shift($tagstoadd);
				

				/* INVALID TAGS */
				if ($prevtag['tagname'] != $nexttag['tagname']) {
					$nexttag['ignore'] = TRUE;
					unset($tagstoadd);
					$tagstoadd = array();
				} else
					$this->n_invalid_tags = $tmp_n_invalid_tags - 1; // Count the new invalid tags
			}


			/* Ignore the tag if needed */
			if ($nexttag['ignore']) {
				$this->ignored_tags[] = $nexttag;
				unset($tagstoadd);
				$tagstoadd = array();
				continue;
			}


			/* Add tags to the DOM */
			array_push($tagstoadd, $nexttag);
			while($tag = array_shift($tagstoadd)) {
				switch ($tag['nature']) {

				case DPARSE_OPENING_TAG:
					if ($prev !== NULL)
						$this->nodes[$prev]->_setNext($this->n_nodes);                                          // Update previous node's next pointer  *** _setNext() is designed for private use, not for the developer
					array_push($breadcrumb, $tag);                                                              // Add a new level to the breadcrumb
					array_push($this->nodes, new DParseDOMNode(count($this->nodes), $this, $parents[$depth -1], $prev, $depth, $breadcrumb));    // Create actual new node
					$prev = NULL;                                                                               // Reset prev pointer, because we enter a new depth level 
					$prevs[$depth] = $this->n_nodes;                                                            // Set next prev pointer for this depth to point to this node
					$parent = $this->n_nodes;                                                                   // Set parent to this node
					$parents[$depth] = $this->n_nodes;
					$depth++;	                                                                                // Increase depth
					$this->n_nodes ++;                                                                          // Increase node pointer
					break;

				case DPARSE_CLOSING_TAG:
					$depth --;                                                                                  // Decrease depth
					$prev = $prevs[$depth];                                                                     // Get prev pointer of the upper level
					$this->nodes[$prev]->_setClosingTagPosition($tag['beginpos'], $tag['length']);              //Set closed tag closing position  *** Again, also for private use
					array_pop($breadcrumb);                                                                     // Remove last level from the breadcrumb
					break;

				case DPARSE_SELF_CLOSING_TAG:
					if ($prev)
						$this->nodes[$prev]->_setNext($this->n_nodes);                                          // Update previous node's next pointer  *** _setNext() is designed for private use, not for the developer
					array_push($breadcrumb, $tag);                                                              // Add a new level to the breadcrumb
					array_push($this->nodes, new DParseDOMNode(count($this->nodes), $this, $parents[$depth -1], $prev, $depth, $breadcrumb));    // Create actual new node
					$this->nodes[$this->n_nodes]->_setClosingTagPosition($tag['beginpos'] + $tag['length'], 0); // Close tag immediatly
					array_pop($breadcrumb);                                                                     // Remove last level from the breadcrumb
					$prevs[$depth] = $this->n_nodes;                                                            // Set next prev pointer for this depth to point to this node
					$prev = $this->n_nodes;                                                                     // Set prev pointer to be pointing to this node
					$this->n_nodes ++;                                                                          // Increase node pointer
					break;

				}
			}

			unset($tagstoadd);
			$tagstoadd = array();

		}

		/* Complete possible incomplete DOM */
		for ($i = 0; $i < $this->n_nodes; $i++)
			if (!$this->nodes[$i]->_complete())
				$this->nodes[$i]->_setClosingTagPosition($this->cursor, 0);
		
		$this->root = $this->nodes[0];
	}


	/* Helper function that browses the document until the next tag token */
	protected function findNextTag()
	{
		$delimiters = $this->whitespaces;
		$delimiters['"'] = 1;
		$delimiters['`'] = 1;
		$delimiters['"'] = 1;

		/* We need to find the character '<' */
		do {
			$beginpos = strpos($this->content, '<', $this->cursor);

			/* Emergency break when the tag does not appear*/
			if ($beginpos === FALSE)
				return NULL;

			$nextchar = $this->content[$beginpos + 1];
			$this->cursor = $beginpos + 1;
		} while ($this->is_noisy() || ($nextchar != '/' && $nextchar != '!' && !preg_match('/[a-zA-Z]/', $nextchar)));

		/* If we can't then the document has been totally reviewed */
		if ($beginpos === FALSE) {
			$this->cursor = $this->getSize();
			return NULL;
		}

		/* We then process until the closing '>' */
		$tag = array();
		$tag ['beginpos'] = $beginpos;
		$inside_quote = FALSE;
		$current_char = $nextchar;

		while ($current_char != '>' || $inside_quote) {


			/* Handle quotes */
			if ($inside_quote && $current_char == $inside_quote)
				$inside_quote = FALSE;
			else if (!$inside_quote && ($current_char == '`' || $current_char == '"' || $current_char == "'"))
				$inside_quote = $current_char;

			/* Build tag name */
			if (!$tagnamefound) {
				if (!isset($delimiters[$current_char]))
					$tag['tagname'] .= $current_char;
				else if ($current_char != "/"){
					$tagnamefound = TRUE;
					$seeking = "attributes";
				}
			}

			/* Handle attributes */
			if ($tagnamefound) {
				/* Parse the attribute name */
				if ($seeking == "attributes") {
					/* Read attribute name */
					if (!isset($delimiters[$current_char]) && $current_char != "=" || $inside_quote)
						$current_attribute .= strtolower($current_char);
					/* Stops when finding a '=' */
					elseif ($current_char == "=")
						$seeking = "value";
					/* Stops when finding a blank */
					elseif ($current_attribute) {
						/* Remove quotes */
						if ($current_attribute[0] == '"' || $current_attribute[0] == "'" || $current_attribute[0] == '`')
							if ($current_attribute[strlen($current_attribute) -1] == $current_attribute[0])
								$current_attribute = substr($current_attribute, 1, -1);
						/* */
						$tag['attr'][$current_attribute] = 1;
						$last_attribute = $current_attribute;
						unset($current_attribute);
					}
				}

				/* Parse the value content */
				elseif ($seeking == "value") {
					/* Reading the value, case when not inside a quote */
					if (!$inside_quote) {
						if (isset($delimiters[$current_char])) {
							if ($current_char == '"' || $current_char == "'" || $current_char == '`')
								$current_value .= $current_char;
							/* Remove quotes */
							if ($current_attribute[0] == '"' || $current_attribute[0] == "'" || $current_attribute[0] == '`')
								if ($current_attribute[strlen($current_attribute) -1] == $current_attribute[0])
									$current_attribute = substr($current_attribute, 1, -1);

							if ($current_value[0] == '"' || $current_value[0] == "'" || $current_value[0] == "`")
								if ($current_value[strlen($current_value) -1] == $current_value[0])
									$current_value = substr($current_value, 1, -1);
							/* */
							$tag['attr'][$current_attribute] = $current_value;
							$last_attribute = $current_attribute;
							unset($current_value);
							unset($current_attribute);
							$seeking = "attributes";
						} else
							$current_value .= $current_char;
					}
					/* Case when inside a quote */
					else
						$current_value .= $current_char;
				}
			}

			/* Emergency break when the tag does not end */
			if ($this->cursor >= $this->getSize())
				return NULL;

			/* Read next character */
			++$this->cursor;
			$current_char = $this->content[$this->cursor];
		}
		++$this->cursor;

		/* Catch possible last attribute */
		if ($current_attribute) {
			if (!$current_value && $current_attribute[strlen($current_attribute) - 1] == "/")
				$current_attribute = substr($current_attribute, 0, -1);
			
			if ($current_attribute) {
				if ($current_attribute[0] == '"' || $current_attribute[0] == "'" || $current_attribute[0] == '`')
					if ($current_attribute[strlen($current_attribute) -1] == $current_attribute[0])
						$current_attribute = substr($current_attribute, 1, -1);
				if ($current_value[0] == '"' || $current_value[0] == "'" || $current_value[0] == "`")
					if ($current_value[strlen($current_value) -1] == $current_value[0])
						$current_value = substr($current_value, 1, -1);
				if (!$current_value)
					$current_value = 1;

				$tag['attr'][$current_attribute] = $current_value;
			}
		}
		
		/* We save the position of the ending '>' */
		$tag['length'] = $this->cursor - $beginpos;
		
		/* We determine the nature of the tag */
		/* Closing tags */
		$data = substr($this->content, $beginpos, $this->cursor);
		if ($nextchar == '/') {
			$tag['nature'] = DPARSE_CLOSING_TAG;
			$tag['tagname'] = substr($tag['tagname'], 1);
		}
		/* Self-closing XML tags */
		if ($current_attribute == "/" || (!$current_attribute && $last_attribute == "/")) {
			if ($this->is_xml)
				$tag['nature'] = DPARSE_SELF_CLOSING_TAG;
		}
		/* Opening tags */
		if (!$tag['nature']) {
			$tag['nature'] = DPARSE_OPENING_TAG;
		}
		/* Special self-closing HTML tags */
		if (!$this->is_xml && isset($this->self_closing_tags[$tag['tagname']])) {
			if ($tag['nature'] == DPARSE_CLOSING_TAG)
				$tag['ignore'] = TRUE;
			$tag['nature'] = DPARSE_SELF_CLOSING_TAG;
		}

		/* Finally we can return the whole tag */
		$tag['tagname'] = strtolower($tag['tagname']);
		return $tag;
	}


	/* Element selector */
	function find($selector, $rootid = -1, $only = NULL, $bool = FALSE, $first = FALSE)
	{
		/* Start the timer, for benchmarking */
		$timer = microtime(true);

		/* Adjustments when input is an element */
		if (is_object($selector)) {
			if (get_class($selector) != "DParseDOMNode" && get_class($selector) != "DParseMetaDOMNode")
				return FALSE;
			$el = $selector;
			$selector = "*";
		}

		/* Scan the input selector */
		$selectors = array();
		$separated_selectors = explode(",", $selector); // Explode the whole selector in an array of diferent selectors
        $single_element_pattern = "/(?:[\s]+)*([\w-\*]*)(?:\#([^#.:\s\[\]]+)|\.([^#.:\s\[\]]+)|\[([^\[\]]+)\]|\:([^#.:\s\[\]]+))?([\s>+~]+)*/im"; // Magic pattern

        
        foreach ($separated_selectors as $index => $sel) {
        	$selectors[$index] = array();
        	$selectors[$index]['fake-levels'] = 0;
        	preg_match_all($single_element_pattern, $sel, $tmpmatches, PREG_SET_ORDER); // $1 = delimiter (whitespace or '>'), $2 = tag, $3 = ID, $4 = class, $5 = attribute, $6 = pseudo-element
        	$i = 0;
        	foreach ($tmpmatches as $tmp) {
        		if (trim($tmp[1])) $selectors[$index][$i]['tagname'][] = strtolower($tmp[1]);
        		if (trim($tmp[2])) $selectors[$index][$i]['id'][] = $tmp[2];
        		if (trim($tmp[3])) $selectors[$index][$i]['class'][] = $tmp[3];
        		if (trim($tmp[4])) { // Explode the attribute selector syntax
        			if (preg_match("/^(?:\s*)([^\[\]]+?)(?:\s*)((?:[><%!~|^$*]?)(?:=))(?:\s*)([^\[\]]+?)(?:\s*)$/", $tmp[4], $attributematches)){
        				foreach ($attributematches as &$a) if ($a[1] && ($a[0] == "'" || $a[0] == '"') && $a[0] == $a[strlen($a)-1]) $a = substr($a, 1, -1);
        				$selectors[$index][$i]['attr'][] = $attributematches;
        			}
        			elseif (preg_match("/^(?:\s*)([^\[\]]+)(?:\s*)$/", $tmp[4], $attributematches)) {
        				foreach ($attributematches as &$a) if ($a[1] && ($a[0] == "'" || $a[0] == '"') && $a[0] == $a[strlen($a)-1]) $a = substr($a, 1, -1);
        				$selectors[$index][$i]['attr'][] = $attributematches;
        			}
        		}
        		if (trim($tmp[5])) {
        			$pseudoparam = strpos($tmp[5], '(');
        			if ($pseudoparam !== FALSE) {
        				$tmp[5] = substr($tmp[5], 0, $pseudoparam);
        				$pseudoparam = substr($tmp[5], $pseudoparam, -1);
        			}
        			$selectors[$index][$i]['pseudo'][] = $tmp[5];
        			$selectors[$index][$i]['pseudoparam'][] = $pseudoparam;
        		}
        		if (!empty($selectors[$index]) && $tmp[6]) {
					$tmp[6] = trim($tmp[6]);
        			if ($tmp[6] ==  "+" || $tmp[6] ==  "~") {
        				$selectors[$index][$i]['shift'] = $tmp[6];
        				$selectors[$index]['fake-levels']++;
        			}
        			else if ($tmp[6] ==  ">")
        				$selectors[$index][$i+1]['arrow'] = '>';
        			$i++;
        		}
        	}
        }

        /* Then find all the matching nodes */
        $nodes = array();
	    if ($rootid != -1) {$rootdepth = $this->nodes[$rootid]->depth(); $breadcrumb_origin = $this->nodes[$rootid]->breadcrumb_size();}
	    else $breadcrumb_origin = 0;
        for ($id = $rootid + 1; $id < count($this->nodes); $id++) {
        	
        	$node = $this->nodes[$id];
        	if ($rootid != -1 && $node->depth() <= $rootdepth)
        		break;

        	if (is_array($only) && !isset($only[$id]))
        		continue;

        	foreach ($selectors as $sel) {

        		$depth = 0;
        		$fake_depth = 0;
        		$sel_size = count($sel) - 1;
        		$breadcrumb_size = $node->breadcrumb_size();

        		for ($tag_index = $breadcrumb_origin; $tag_index < $breadcrumb_size; $tag_index ++) {
        			
        			if ( $depth == $sel_size - 1 && $sel[$depth]['arrow'] && $tag_index != $breadcrumb_size -1 ) break;
        			if ( $selsize - $sel['fake-levels'] - $depth + $fake_depth > $breadcrumb_size - $tag_index ) break;
        			while ( $sel[$depth]['shift'] ) {$depth++; $fake_depth++; $shift_queue++;}
        			if ( $depth == $sel_size - 1) $tag_index = $breadcrumb_size - 1;

        			$current_tag = $node->breadcrumb_element($tag_index);
        			$ok = true;

        			if ( !$this->contains($sel[$depth]['tagname'], $current_tag['tagname']) ) $ok = false;
        			else if ( !$this->contains($sel[$depth]['id'], $current_tag['attr']['id']) ) $ok = false;
        			else if ( !$this->contains($sel[$depth]['class'], $current_tag['attr']['class']) ) $ok = false;
        			else if ( !$this->contains_attributes($sel[$depth]['attr'], $current_tag['attr']) ) $ok = false;
        		//	else if ( !$this->contains_pseudo_selectors($sel[$depth]['pseudo'], $sel[$depth]['pseudoparam'], $node)) $ok = false;
        			else if ( $sel[$depth-1]['shift']) {
        				$tmpshift_queue = $shift_queue;
        				$tmpnode = $node;
        				for ($i = 0; $i < $breadcrumb_size -1 -$tag_index; $i++) {
        					$tmpnode = $tmpnode->parent();
        					if (!$tmpnode) {$ok = false; break;}
        				}
        				if ($tmpnode)
	        				$tmpnode = $tmpnode->prev();

        				while ($tmpshift_queue > 0 && $ok) {
        					$tmpdepth = $depth - $shift_queue + $tmpshift_queue;
        					if (!$tmpnode) {
        						$ok = false;
        						break;
        					}
        					$tmptag = $tmpnode->breadcrumb_element($tag_index);
        					if ($sel[$tmpdepth-1]['shift'] == "+") {
        						if ( !$this->contains($sel[$tmpdepth - 1]['tagname'], $tmptag['tagname']) ) $ok = false;
			        			else if ( !$this->contains($sel[$tmpdepth - 1]['id'], $tmptag['attr']['id']) ) $ok = false;
			        			else if ( !$this->contains($sel[$tmpdepth - 1]['class'], $tmptag['attr']['class']) ) $ok = false;
			        			else if ( !$this->contains_attributes($sel[$tmpdepth - 1]['attr'], $tmptag['attr']) ) $ok = false;
			        			$tmpnode = $tmpnode->prev();			        			
        					} else {
        						do {
        							$ok = true;
        							$tmptag = $tmpnode->breadcrumb_element($tag_index);
        							if ( !$this->contains($sel[$tmpdepth - 1]['tagname'], $tmptag['tagname']) ) $ok = false;
			        				else if ( !$this->contains($sel[$tmpdepth - 1]['id'], $tmptag['attr']['id']) ) $ok = false;
			        				else if ( !$this->contains($sel[$tmpdepth - 1]['class'], $tmptag['attr']['class']) ) $ok = false;
			        				else if ( !$this->contains_attributes($sel[$tmpdepth - 1]['attr'], $tmptag['attr']) ) $ok = false;
			        				$tmpnode = $tmpnode->prev();
        						} while (!$ok && $tmpnode);
        						if ($tmpnode)
	        						$ok = true;
        					}
        					$tmpshift_queue--;
        				}
        			}

        			if ( $sel[$depth]['arrow'] && !$ok ) {$depth --;}
        			if ( $sel[$depth-1]['shift'] && !$ok ) {$depth -= $shift_queue; $fake_depth -= $shift_queue;}
        			$shift_queue = 0;

        			if ( $ok ) $depth++;
        			if ( $depth == $sel_size ) {
        				$nodes[] = $node;
        				if ($first) break 3;
        				break 2;
        			}

        		}

        	}
        }

        /* Special overrding when input selector was an object */
        if ($el) {
        	if (get_class($el) == "DParseDOMNode")
        		foreach ($nodes as $i => $n)
        			if ($n->index() != $el->index())
        				unset($nodes[$i]);
        	if (get_class($el) == "DParseMetaDOMNode") {
        		foreach ($nodes as $i => $n) {
        			$ok = false;
        			foreach ($el as $e) {
        				if ($n->index() == $e->index())
        					$ok = true;
        			}
        			if (!$ok)
        				unset($nodes[$i]);
        		}
        	}

        }

	    $timer = microtime(true) - $timer;
	    $logger = $this->logger;
	    $logger->log('');
	    $logger->log("Performing CSS query: $selector"); 
	    $logger->log("Found " . count($nodes) . " node" . (count($nodes) == 1 ? "" : "s") . " in " . $timer . " seconds");
		$logger->log("Memory peak usage: " . number_format(memory_get_peak_usage(), 0, ".", " ") . " bytes");
		if (empty($nodes)) return FALSE;
        else return ($bool) ? !empty($nodes) : new DParseMetaDOMNode($nodes);
	}



	/* Quick helper function to match array elements inside a string */
	protected function contains($search, $string)
	{
		if (!isset($search))
			return true;

		foreach ($search as $s) {

			if ($s == "*")
				continue;
			$pos = strpos($string, $s);
			$len = strlen($s);
			if ( $pos !== 0 || (!isset($this->whitespaces[$string[$pos+$len]]) && $string[$pos+$len]) )
				return false;
		}

		return true;
	}

	/* Quick helper function to match attributes respecting the CSS selector syntax */
	protected function contains_attributes($search, &$attributes)
	{
		if (!isset($search))
			return true;

		foreach ($search as $s) {
			switch ($s[2]) {
				case '%=': if (!preg_match($s[3], $attributes[$s[1]])) return false; break;
				case '|=': if (!preg_match('/\b'.preg_quote($s[3]).'[\-\s]?/s', $attributes[$s[1]])) return false; break;
				case '~=': if (!preg_match('/\b'.preg_quote($s[3]).'\b/s', $attributes[$s[1]])) return false; break;
				case '*=': if (strpos($attributes[$s[1]],$s[3]) === FALSE) return false; break;
				case '$=': if (substr($attributes[$s[1]], -strlen($s[3])) != $s[3]) return false; break;
				case '^=': if (strpos($attributes[$s[1]],$s[3]) !== 0) return false; break;
				case '!=': if ($attributes[$s[1]] == $s[3]) return false; break;
				case '=':  if ($attributes[$s[1]] != $s[3]) return false; break;
				case '>=': if ($attributes[$s[1]] < $s[3]) return false; break;
				case '<=': if ($attributes[$s[1]] > $s[3]) return false; break;
				default: if (!isset($attributes[$s[1]])) return false; break;
			}
		}

		return true;
	}

	/* Quick helper function to match pseudo selectors */
	/*protected function contains_pseudo_selectors($search, $param, $node)
	{
		if (!isset($search))
			return true;

		foreach ($search as $id => $s) {
			switch ($s) {
				case 'before':	case ':before':case 'after':case ':after':case 'active':case 'hover':case 'focus':	case 'visited': case 'link': break;
				case 'checked': if (!$node->checked) return false; break;
				case 'disabled': if (!$node->disabled) return false; break;
				case 'empty': if ($node->htmlLength()) return false; break;
				case 'enabled': if ($node->disabled) return false; break;
				case 'in-range': if ($node->value > $node->max || $node->value < $node->min) return false; break;
				case 'invalid': if (trim($node->value)) return false; break;
				case 'lang': if (strtolower($node->lang) != strtolower($param[$id])) return false;
				case 'last-child': if ($node->next) return false; break;
				case 'last-of-type': while ($n = $node->next) {
										$e = $n->breadcrumb_element($n->breadcrumb_size();
										if ($e['tagname'] == $param[$id])
											return false;
									}
									break;
				default: return false;
			}
		}

		return true;
	}*/


	/* GETTERS */
	/* Nodes */
	function __invoke($selector) { return $this->find($selector); }
	function root() { return $this->root; }
	/* Logger */
	function getLogger() { return $this->logger; }

	/* Content */
	function __toString() { return $this->content; }
	function getRawContent() { return $this->content; }
	function showRawContent() { echo $this->content; }
	function saveRawContent($filename) { file_put_contents($filename, $this->content); }

	/* Returns the document size */
	function getSize() { return strlen($this->content); }

	/* Charset & whitespace stripping */
	function getWhitespaceStripping() { return $this->strip_whitespaces; }
	function getInputCharset() { return $this->input_charset; }
	function getOutputCharset() { return $this->output_charset; }

	/* Noise */
	function getNoise()
	{
		$noise = array();
		foreach ($this->noisy_tags as $n)
			array_push($noise, substr($this->content, $n['beginpos'], $n['length']));
		return $noise;
	}


	/* SETTERS */
	function setWhitespaceStripping($bool) { $this->strip_whitespaces = $bool; }
	function setInputCharset($charset) { $this->input_charset = $charset; }
	function setOutputCharset($charset) { $this->output_charset = $charset; }
	function update($begin, $end, $closebegin, $closeend, $old, $new, $id, $update_tag, $update_attribute = FALSE, $attr_name = FALSE, $value = FALSE)
	{
		$tag = substr($this->content, $begin, $end);
		if ($update_tag) {
			$closetag = substr($this->content, $closebegin, $closeend);
			$newtag = preg_replace("/(<\s*)($old)/i", "$1$new", $tag);
			$newclosetag = preg_replace("/(<\s*\/\s*)($old)/i", "$1$new", $closetag);
			$this->content = substr($this->content, 0, $begin)
			 . $newtag . substr($this->content, $begin + $end, $closebegin - $begin - $end)
			 . $newclosetag
			 . substr($this->content, $closebegin + $closeend);
			 $offset = 2*(strlen($new)-strlen($old));

			for ($i = $id+1; $i < $this->n_nodes; $i++)
				$this->nodes[$i]->_shiftTagPosition($offset, 0, TRUE, $closebegin)->_shiftTagPosition($offset, 0, FALSE, $closebegin);
			$this->nodes[$id]->_shiftTagPosition(0, $offset/2, TRUE)->_shiftTagPosition($offset/2, $offset/2, FALSE);
			$i = $id+1;
			while ($i < $this->n_nodes && $this->nodes[$i]->depth() > $this->nodes[$id]->depth()) {
				$t = &$this->nodes[$i++]->breadcrumb();
				$t[$this->nodes[$id]->depth()]["tagname"] = $new;
			}
		}
		
		else if ($update_attribute) {
			$boundary = '"';
			if (strpos($value, '"') !== FALSE) $boundary = "'";
			if ($boundary == "'" && strpos($value, "'") !== FALSE) $boundary = "`";
			if ($boundary == "`" && strpos($value, "`") !== FALSE) return;
			for ($cursor = 0; $cursor < strlen($tag); $cursor++) {
				if (!$quote && ($tag[$cursor] == "'" || $tag[$cursor] == "`" || $tag[$cursor] == '"')) {
					$quote = $tag[$cursor];
					continue;
				}
				else if ($quote && $tag[$cursor] == $quote) {
					$quote = FALSE;
					if (isset($attr_begin))
						$attr_end = $cursor + 1;
				}
				$attr_quote = preg_quote($attr_name);
				if (!$quote && !isset($attr_begin) && preg_match("/^".$attr_quote."[\s=\/>]/i", substr($tag, $cursor)))
					$attr_begin = $cursor;
				else if (!$quote && isset($attr_begin) && !isset($attr_end) && preg_match("/^[\s\/>]$/", $tag[$cursor]))
					$attr_end = $cursor;

				if (isset($attr_begin) && isset($attr_end))
					break;
			}


			if (isset($attr_begin) && !isset($attr_end)) return;
			if ($value === FALSE || $value === NULL) $new_attr = '';
			else if ($value !== TRUE) $new_attr = $attr_name.'='.$boundary.$value.$boundary;
			else if ($value === TRUE) $new_attr = $attr_name." ";
			if (!isset($attr_begin) && !isset($attr_end)) { $attr_begin = $attr_end = $end-1; $new_attr = " ".$new_attr; }

			$offset = strlen($new_attr) - strlen(substr($tag, $attr_begin, $attr_end - $attr_begin));
			$this->content = substr($this->content, 0, $begin + $attr_begin) . $new_attr . substr($this->content, $begin + $attr_end);

			for ($i = $id+1; $i < $this->n_nodes; $i++)
				$this->nodes[$i]->_shiftTagPosition($offset, 0, TRUE)->_shiftTagPosition($offset, 0, FALSE);
			$this->nodes[$id]->_shiftTagPosition(0, $offset, TRUE)->_shiftTagPosition($offset, 0, FALSE);
			$i = $id+1;
			while ($i < $this->n_nodes && $this->nodes[$i]->depth() > $this->nodes[$id]->depth()) {
				$t = &$this->nodes[$i++]->breadcrumb();
				if ($value)
					$t[$this->nodes[$id]->depth()]["attr"][$attr_name] = $value;
				else
					unset($t[$this->nodes[$id]->depth()]["attr"][$attr_name]);
			}
		}

	}

	/* END OF CLASS */
}












/** dParse DOM Node Class **/
class DParseDOMNode
{

	private $dom;
	private $parent;
	private $next;
	private $prev;
	private $tagdata;
	private $depth;
	private $breadcrumb;
	private $complete;
	private $id;

	/* Constructor */
	function __construct($id, $dom, $parent, $prev, $depth, $breadcrumb)
	{
		$this->id = $id;
		$this->complete = FALSE;
		$this->dom = $dom;
		$this->parent = $parent;
		$this->prev = $prev;
		$this->depth = $depth;
		$this->breadcrumb = $breadcrumb;
		$this->tagdata = array_pop($breadcrumb);

		for ($i = 0; $i < count($this->breadcrumb); $i++) {
			unset($this->breadcrumb[$i]['beginpos']);
			unset($this->breadcrumb[$i]['length']);
			unset($this->breadcrumb[$i]['nature']);
		}
	}

	/* Private functions, not declared as private because they are needed, but they should not be used by the developer */
	function _setNext($next) { $this->next = $next; }

	function _shiftTagPosition($pos, $length, $begin, $closebegin = FALSE)
	{
		if ($closebegin !== FALSE && $closebegin > $this->tagdata['beginpos']) {
			$pos/=2;
			$length/=2;
		}
		if ($begin) {
			$this->tagdata['beginpos'] += $pos;
			$this->tagdata['length'] += $length;
		} else {
			$this->tagdata['closepos'] += $pos;
			$this->tagdata['closelength'] += $length;
		}
		return $this;
	}

	function _setClosingTagPosition($pos, $length)
	{
		$this->complete = TRUE;
		$this->tagdata['closepos'] = $pos;
		$this->tagdata['closelength'] = $length;
	}

	function _complete() { return $this->complete; }
	function _dom() { return $this->dom; }


	/* GETTERS */
	function index() { return $this->id; }
	function length() { return 1; }
	function tagName($name = FALSE) { if ($name) return $this->setTagName($name); else return $this->tagdata['tagname']; }
	function attributes() { return $this->tagdata['attr']; }
	function depth() { return $this->depth; }
	function attr($attr, $val = NULL) { $attr = strtolower($attr); if (func_num_args() >= 2) return $this->setAttr($attr, $val); return $this->tagdata['attr'][$attr]; }
	function prop($attr, $val = NULL) { return $this->attr($attr, $val); }
	function name() { return $this->tagdata['tagname']; }
	function &breadcrumb() { return $this->breadcrumb; }
	function breadcrumb_size() { return count($this->breadcrumb); }
	function &breadcrumb_element($i) { return $this->breadcrumb[$i]; }
	function val($value = FALSE)
	{ 
		// Also need to handle textareas, select
		return $this->tagdata['attr']['value'];
	}
	function html()
	{ 
		$html = substr($this->dom->getRawContent(), $this->tagdata['beginpos'] + $this->tagdata['length'], $this->tagdata['closepos'] - $this->tagdata['beginpos'] - $this->tagdata['length']);
		if ($this->dom->getWhitespaceStripping())
			$html = preg_replace('/\s+/', ' ', trim(rtrim($html)));
		if ($this->dom->getInputCharset() && $this->dom->getOutputCharset())
			$html= mb_convert_encoding($html, $this->dom->getInputCharset(), $this->dom->getOutputCharset()); 
		return $html;
	}
	function htmlLength() { return strlen($this->html()); }
	function outerHTML()
	{
		$html = substr($this->dom->getRawContent(), $this->tagdata['beginpos'], $this->tagdata['closepos'] + $this->tagdata['closelength'] - $this->tagdata['beginpos']);
		if ($this->dom->getWhitespaceStripping())
			$html = preg_replace('/\s+/', ' ', $html);
		if ($this->dom->getInputCharset() && $this->dom->getOutputCharset())
			$html= mb_convert_encoding($html, $this->dom->getInputCharset(), $this->dom->getOutputCharset()); 
		return $html;
	}
	function outerHTMLLength() { return strlen($this->outerHtml()); }
	function text() { return strip_tags($this->html()); }
	function textLength() { return strlen(strip_tags($this->html())); }
	function __get($attr) { return $this->attr($attr); }
	function __toString() { return $this->outerHTML(); }



	/* SEARCHERS */
	/*function download()*/
	function find($selector)
	{ 	
		if (is_string($selector) || is_object($selector)) return $this->children($selector);
		else return FALSE;
 	}
	function __invoke($selector) { return $this->find($selector); }
	function parent($selector = NULL)
	{
		$nodes = array();
		if ($selector === NULL) {
			if ($this->parent === NULL) return FALSE;
			else $nodes[] = $this->dom->nodes[$this->parent];
		}
		else if (is_int($selector)) {
			$p = $this->parents();
			if (!$p) return FALSE;
			if ($selector >= 0)
				$nodes[] = $p->eq($selector);
			else
				$nodes[] = $p->eq($p->length()+$selector);
		}
		else if (is_string($selector)) {
			$ids = array();
			$p = $this;
			while (($p = $p->parent()))
				$ids[$p->index()] = 1;
			return $this->dom->find($selector, -1, $ids);
		}

		else if (is_object($selector)) {
			$p = $this;
			if (get_class($selector) == "DParseDOMNode")
				while($p = $p->parent())
					if ($p->index() == $selector->index())
						$nodes[] = $p;

			if (get_class($selector) == "DParseMetaDOMNode")
				while($p = $p->parent())
					foreach($selector as $sel)
						if ($p->index() == $sel->index())
							$nodes[] = $p;
		}

		if (empty($nodes)) return FALSE;
		else return new DParseMetaDOMNode($nodes);
	}
	function parents()
	{
		$nodes = array();
		$p = $this;
		while (($p = $p->parent()))
			$nodes[] = $p;

		if (empty($nodes)) return FALSE;
		else return new DParseMetaDOMNode($nodes);
	}
	function parentsUntil($selector)
	{
		if (is_int($selector)) {
			if ($selector == 0) return FALSE;
			$p = $this->parents();
			if (!$p) return FALSE;
			$nodes = array();
			if ($selector > 0)
				for($i = 0; $i < $selector; $i++)
					$nodes[] = $p->eq($i);
			if ($selector < 0)
				for($i = 0; $i < $p->length() + $selector; $i++)
					$nodes[] = $p->eq($i);
			
			if (empty($nodes)) return FALSE;
			else return new DParseMetaDOMNode($nodes);
		}

		if (is_object($selector)) {
			if (get_class($selector) != "DParseDOMNode" && get_class($selector) != "DParseMetaDOMNode")
				return FALSE;
			$nodes = array();
			$p = $this;
			if (get_class($selector) == "DParseDOMNode")
				while ($p = $p->parent()) {
					if ($p->index() != $selector->index()) {
						$nodes[] = $p;
					} else break;
				}
			
			else if (get_class($selector) == "DParseMetaDOMNode") {
				while ($p = $p->parent()) {
					$ok = true;
					foreach ($selector as $sel)
						if ($p->index() == $sel->index())
							$ok = false;
					if ($ok)
						$nodes[] = $p;
					else break;
				}
			}

			if (empty($nodes)) return FALSE;
			else return new DParseMetaDOMNode($nodes);		
		}

		$ids = array();
		$p = $this;
		while (($p = $p->parent()))
			$ids[$p->index()] = 1;
		$res = $this->dom->find($selector, -1, $ids);
		if (!$res)
			return $this->parents();
		else {
			$nodes = array();
			$limit = $res->elem($res->length() - 1)->index();
			unset($res);
			foreach($ids as $i => $d) {
				if ($i == $limit)
					break;
				else
					$nodes[] = $this->dom->nodes[$i];
			}
			if (empty($nodes)) return FALSE;
			else return new DParseMetaDOMNode($nodes);
		}
	}


	function prev($selector = NULL)
	{
		$nodes = array();
		if ($selector === NULL) {
			if ($this->prev === NULL) return FALSE;
			else $nodes[] = $this->dom->nodes[$this->prev];
		}
		else if (is_int($selector)) {
			$p = $this->prevAll();
			if (!$p) return FALSE;
			if ($selector >= 0)
				$nodes[] = $p->eq($selector);
			else
				$nodes[] = $p->eq($p->length()+$selector);
		}
		else if (is_string($selector)) {
			$ids = array();
			$p = $this;
			while (($p = $p->prev()))
				$ids[$p->index()] = 1;
			return $this->dom->find($selector, -1, $ids);
		}

		else if (is_object($selector)) {
			$p = $this;
			if (get_class($selector) == "DParseDOMNode")
				while($p = $p->prev())
					if ($p->index() == $selector->index())
						$nodes[] = $p;

			if (get_class($selector) == "DParseMetaDOMNode")
				while($p = $p->prev())
					foreach($selector as $sel)
						if ($p->index() == $sel->index())
							$nodes[] = $p;
		}

		if (empty($nodes)) return FALSE;
		else return new DParseMetaDOMNode($nodes);
	}
	function prevAll()
	{
		$nodes = array();
		$p = $this;
		while (($p = $p->prev()))
			$nodes[] = $p;

		if (empty($nodes)) return FALSE;
		else return new DParseMetaDOMNode($nodes);
	}
	function prevUntil($selector)
	{
		if (is_int($selector)) {
			if ($selector == 0) return FALSE;
			$p = $this->prevAll();
			if (!$p) return FALSE;
			$nodes = array();
			if ($selector > 0)
				for($i = 0; $i < $selector; $i++)
					$nodes[] = $p->eq($i);
			if ($selector < 0)
				for($i = 0; $i < $p->length() + $selector; $i++)
					$nodes[] = $p->eq($i);
			
			if (empty($nodes)) return FALSE;
			else return new DParseMetaDOMNode($nodes);
		}

		if (is_object($selector)) {
			if (get_class($selector) != "DParseDOMNode" && get_class($selector) != "DParseMetaDOMNode")
				return FALSE;
			$nodes = array();
			$p = $this;
			if (get_class($selector) == "DParseDOMNode")
				while ($p = $p->prev()) {
					if ($p->index() != $selector->index()) {
						$nodes[] = $p;
					} else break;
				}
			
			else if (get_class($selector) == "DParseMetaDOMNode") {
				while ($p = $p->prev()) {
					$ok = true;
					foreach ($selector as $sel)
						if ($p->index() == $sel->index())
							$ok = false;
					if ($ok)
						$nodes[] = $p;
					else break;
				}
			}

			if (empty($nodes)) return FALSE;
			else return new DParseMetaDOMNode($nodes);		
		}


		$ids = array();
		$p = $this;
		while (($p = $p->prev()))
			$ids[$p->index()] = 1;
		$res = $this->dom->find($selector, -1, $ids);
		if (!$res)
			return $this->prevAll();
		else {
			$nodes = array();
			$limit = $res->elem($res->length() - 1)->index();
			unset($res);
			foreach($ids as $i => $d) {
				if ($i == $limit)
					break;
				else
					$nodes[] = $this->dom->nodes[$i];
			}
			if (empty($nodes)) return FALSE;
			else return new DParseMetaDOMNode($nodes);
		}
	}


	function next($selector = NULL)
	{
		$nodes = array();
		if ($selector === NULL) {
			if ($this->next === NULL) return FALSE;
			else $nodes[] = $this->dom->nodes[$this->next];
		}
		else if (is_int($selector)) {
			$p = $this->nextAll();
			if (!$p) return FALSE;
			if ($selector >= 0)
				$nodes[] = $p->eq($selector);
			else
				$nodes[] = $p->eq($p->length()+$selector);
		}
		else if (is_string($selector)) {
			$ids = array();
			$p = $this;
			while (($p = $p->next()))
				$ids[$p->index()] = 1;
			return $this->dom->find($selector, -1, $ids);
		}

		else if (is_object($selector)) {
			$p = $this;
			if (get_class($selector) == "DParseDOMNode")
				while($p = $p->next())
					if ($p->index() == $selector->index())
						$nodes[] = $p;

			if (get_class($selector) == "DParseMetaDOMNode")
				while($p = $p->next())
					foreach($selector as $sel)
						if ($p->index() == $sel->index())
							$nodes[] = $p;
		}

		if (empty($nodes)) return FALSE;
		else return new DParseMetaDOMNode($nodes);
	}
	function nextAll()
	{
		$nodes = array();
		$p = $this;
		while (($p = $p->next()))
			$nodes[] = $p;

		if (empty($nodes)) return FALSE;
		else return new DParseMetaDOMNode($nodes);
	}
	function nextUntil($selector)
	{
		if (is_int($selector)) {
			if ($selector == 0) return FALSE;
			$p = $this->nextAll();
			if (!$p) return FALSE;
			$nodes = array();
			if ($selector > 0)
				for($i = 0; $i < $selector; $i++)
					$nodes[] = $p->eq($i);
			if ($selector < 0)
				for($i = 0; $i < $p->length() + $selector; $i++)
					$nodes[] = $p->eq($i);
			
			if (empty($nodes)) return FALSE;
			else return new DParseMetaDOMNode($nodes);
		}

		if (is_object($selector)) {
			if (get_class($selector) != "DParseDOMNode" && get_class($selector) != "DParseMetaDOMNode")
				return FALSE;
			$nodes = array();
			$p = $this;
			if (get_class($selector) == "DParseDOMNode")
				while ($p = $p->next()) {
					if ($p->index() != $selector->index()) {
						$nodes[] = $p;
					} else break;
				}
			
			else if (get_class($selector) == "DParseMetaDOMNode") {
				while ($p = $p->next()) {
					$ok = true;
					foreach ($selector as $sel)
						if ($p->index() == $sel->index())
							$ok = false;
					if ($ok)
						$nodes[] = $p;
					else break;
				}
			}

			if (empty($nodes)) return FALSE;
			else return new DParseMetaDOMNode($nodes);		
		}

		$ids = array();
		$p = $this;
		while (($p = $p->next()))
			$ids[$p->index()] = 1;
		$res = $this->dom->find($selector, -1, $ids);
		if (!$res)
			return $this->nextAll();
		else {
			$nodes = array();
			$limit = $res->elem($res->length() - 1)->index();
			unset($res);
			foreach($ids as $i => $d) {
				if ($i == $limit)
					break;
				else
					$nodes[] = $this->dom->nodes[$i];
			}
			if (empty($nodes)) return FALSE;
			else return new DParseMetaDOMNode($nodes);
		}
	}


	function children($selector = NULL)
	{
		$nodes = array();

		if ($selector === NULL)
			$selector = "*";

		if (is_int($selector)) {
			$c = $this->children();
			if (!$c) return FALSE;
			if ($selector < 0) $selector = $c->length() + $selector;
			$nodes[] = $c->eq($selector);
		}

		if (is_object($selector)) {
			if ($c = $this->children())
				$nodes[] = $c->eq($selector);
		}

		else return $this->dom->find($selector, $this->id);

		if (empty($nodes)) return FALSE;
		else return new DParseMetaDOMNode($nodes);
	}

	function is($selector)
	{
		return $this->dom->find($selector, -1, array($this->index() => 1), TRUE);
	}


	function contains($selector)
	{
		$res = $this->dom->find($selector, -1, array($this->index() => 1), TRUE);
		return $res;
	}

	function has($selector = NULL)
	{
		if (!$e = $this->children()) return FALSE;
		foreach ($e as $ee)
			if ($ee->is($selector))
				return new DParseMetaDOMNode(array(0 => $this));
		return FALSE;
	}

	function hasChild($selector = NULL)
	{
		return $this->has($selector);
	}

	function hasParent($selector = NULL)
	{
		if (!$e = $this->parents()) return FALSE;
		foreach ($e as $ee)
			if ($ee->is($selector))
				return new DParseMetaDOMNode(array(0 => $this));
		return FALSE;
	}

	function hasPrev($selector = NULL)
	{
		if (!$e = $this->prevAll()) return FALSE;
		foreach ($e as $ee)
			if ($ee->is($selector))
				return new DParseMetaDOMNode(array(0 => $this));
		return FALSE;
	}

	function hasNext($selector = NULL)
	{
		if (!$e = $this->nextAll()) return FALSE;
		foreach ($e as $ee)
			if ($ee->is($selector))
				return new DParseMetaDOMNode(array(0 => $this));
		return FALSE;
	}





	/* SETTERS */
	function setTagName($name)
	{
		$name = str_replace(array(" ","\r", "\n", "\t"), '', $name);
		if (!strlen($name)) return $this;
		$name = strtolower($name);

		$this->dom->update($this->tagdata['beginpos'], $this->tagdata['length'], $this->tagdata['closepos'], $this->tagdata['closelength'], $this->tagdata['tagname'], $name, $this->id, TRUE);
		$this->tagdata['tagname'] = $name;
		$this->breadcrumb[$this->depth]["tagname"] = $name;
		return $this;
	}
	function setAttr($attr, $value)
	{
		$attr2 = str_replace(array(" ","\r", "\n", "\t"), '', $attr);
		if (!strlen($attr2)) return $this;
		$attr = strtolower($attr);

		if ($value === '') $value = TRUE;
		$this->dom->update($this->tagdata['beginpos'], $this->tagdata['length'], NULL, NULL, NULL, NULL, $this->id, FALSE, TRUE, $attr, $value);

		if ($value || $value === 0 || $value === "0") {
			$this->tagdata['attr'][$attr] = $value;
			$this->breadcrumb[$this->depth]['attr'][$attr] = $value;
		} else {
			unset($this->tagdata['attr'][$attr]);
			unset($this->breadcrumb[$this->depth]['attr'][$attr]);
		}
		return $this;

	}
	function removeAttr($attr) { return $this->setAttr($attr, FALSE); }
	function __set($attr, $val) { return $this->setAttr($attr, $val); }
	function addClass($class)
	{
		$class2 = str_replace(array(" ","\r", "\n", "\t"), '', $class);
		if (!strlen($class2)) return $this;

		preg_match_all("/\s*([^\s]*)\s*/", $class, $classes);
		foreach ($classes[1] as $cl) {
			$c = $this->tagdata["attr"]["class"];
			if (preg_match("/\s*".preg_quote($cl, "/")."\s*/", $c)) continue;
			if (!strlen(str_replace(array(" ","\r", "\n", "\t"), '', $cl))) continue;
			else $c .= " ".$cl;
			$this->setAttr("class", $c);
		}

		return $this;
	}
	function removeClass($class)
	{
		$class2 = str_replace(array(" ","\r", "\n", "\t"), '', $class);
		if (!strlen($class2)) return $this;

		preg_match_all("/\s*([^\s]*)\s*/", $class, $classes);
		foreach ($classes[1] as $cl) {
			$c = $this->tagdata["attr"]["class"];
			$c = preg_replace("/\s*".preg_quote($cl, "/")."\s*/", '', $c); echo("\n!".$c."\n");
			if (!strlen(str_replace(array(" ","\n","\r","\t"), '', $c))) $c = FALSE;
			$this->setAttr("class", $c);
		}

		return $this;
	}

	function remove()
	{
		$c = $this->children();
	}
	
	/*function val($value)
	function html($html)
	function prepend()
	function append()
	function remove()*/

	/* END OF CLASS */
}










/** dParse Meta DOM Node Class
 *  Provides a handy node array abstraction for multiple nodes
 *  but behaves like a direct result when there is only one node */
class DParseMetaDOMNode
{
	private $_count;
	function __construct($nodes)
	{
		$j = 0;
		for ($i = 0; $i < count($nodes); $i++)
			if ($nodes[$i])
				$this->{'_dParse_node'.$j++} = $nodes[$i];
		$this->_count = $j;
	}



	private function merge($metanodes)
	{
		$ids = array();
		$nodes = array();
		foreach ($metanodes as $m) {
			if ($m) {
				foreach ($m as $n) {
					if (is_object($n) && !isset($ids[$n->index()])) {
						$ids[$n->index()] = 1;
						$nodes[] = $n;
					}
				}
			}
		}

		if (empty($nodes)) return FALSE;
		else return new DParseMetaDOMNode($nodes);
	}


	function length () { return $this->_count; }
	function eq($elem) { return $this->elem($elem); }
	function elem($elem)
	{
		if (is_string($elem)) {
			$elem = $this->_dParse_node0->_dom()->find($elem);
			if (!$elem) return FALSE;
		}

		if (is_object($elem)) {
			if (get_class($elem) != "DParseDOMNode" && get_class($elem) != "DParseMetaDOMNode")
				return FALSE;

			if (get_class($elem) == "DParseDOMNode")
				foreach ($this as $t)
					if (is_object($t) && $t->index() == $elem->index())
						return $elem;

			if (get_class($elem) == "DParseMetaDOMNode") {
				$nodes = array();
				foreach ($this as $t) {
					if (is_object($t)) {
						$ok = false;
						foreach ($elem as $e)
							if (is_object($e) && $e->index() == $t->index())
								$ok = true;
						if ($ok)
							$nodes[] = $t;
					}
				}
			if (empty($nodes)) return FALSE;
			else return new DParseMetaDOMNode($nodes);
			}
		}

		if (is_int($elem) && $elem < 0) $elem += $this->_count; return is_int($elem) ? $tmp = $this->{'_dParse_node'.$elem} : FALSE;

		return FALSE;
	}
	function __call($name, $arguments)
	{
		$ret = array();
		for ($i = 0; $i < $this->_count; $i++) {
			if (count($arguments) == 0)
				$tmp = $this->{'_dParse_node'.$i}->{$name}();
			else if (count($arguments) == 1)
				$tmp = $this->{'_dParse_node'.$i}->{$name}($arguments[0]);
			else if (count($arguments) == 2)
				$tmp = $this->{'_dParse_node'.$i}->{$name}($arguments[0], $arguments[1]);
			if (is_object($tmp))
				$merge_needed = TRUE;
			$ret[] = $tmp;
		}

		if (strpos($name, "has") !== FALSE || strpos($name, "children") !== FALSE || strpos($name, "find") !== FALSE || strpos($name, "parent") !== FALSE || strpos($name, "next") !== FALSE || strpos($name, "prev") !== FALSE) return $this->merge($ret);
		else if (is_object($ret[0]) && get_class($ret[0]) == "DParseDOMNode") return $this;
		else if (count($ret) == 1) return $ret[0];
		else return $ret;
	}
	function __invoke($selector)
	{
		if (is_string($selector) || is_object($selector)) return $this->children($selector);
		else return $this->eq($selector);
	}
	function __get($attr) { return $this->attr($attr); }
	function __set($attr, $value) { if (strpos($attr, '_dParse_node') !== FALSE) $this->{$attr} = $value; else return $this->attr($attr, $value); }
	function __toString()
	{
		if ($this->_count == 1)
			return $this->_dParse_node0->outerHTML();

		if ($this->_count != 1) {
			$tmpnodes = array();
			for($i = 0; $i < $this->_count; $i++)
				$tmpnodes[] = $this->{'_dParse_node'.$i}->outerHTML();
			$str = print_r($tmpnodes, true);
			unset($tmpnodes);
			return $str;
		}
	}

	/* END OF CLASS */
}


/* REMAINS
http://api.jquery.com/category/manipulation/

Write smart method:
.val(), .val($value)
.download($param) x2

support pseudo CSS selectors + add custom selectors
charset also auto detect when xml
better logger

fully test & debug
Add noob-proof comment

write APIS for basic website like wikipedia
Write the Doc 
*/
