<?php
// **Docco.php** is a PHP port of [Docco][do], the quick-and-dirty,
// hundred-line-long, literate-programming-style documentation generator.
// 
// Docco.php reads PHP source files and produces annotated source documentation
// in HTML format. Comments are formatted with [Markdown][md] and presented
// alongside syntax highlighted code so as to give an annotation effect.
// 
// Install Docco.php by placing it in the source directory.
// 
// Pointing your browser to Docco.php will generate documentation for a set
// of PHP source files defined in the Docco.php file.
// 
// The HTML files are written to the current working directory.
// 
// [do]: http://github.com/asifr/mdoc-php/
// [md]: http://daringfireball.net/projects/markdown/

// ### Prerequisites

// We'll need a Markdown library. [PHP Markdown][pmd], if we're lucky. Otherwise,
// issue a warning. Markdown.php should be in the same directory as Docco.php.
// 
// [pmd]: http://michelf.com/projects/php-markdown/

// ### Usage

// $docco = new Docco('filename.php',array(),array(
// 		'langaage'		=> 'matlab',
// 		'comment_chars'	=> '%'
// ));
// $docco->save();

try {
	require_once('Markdown.php');
} catch (Exception $e) {
	die('Markdown.php is not in the current working dircetory.');
}

// ### Public Interface

// `Docco` takes a source `filename`, an optional list of source filenames
// for other documentation sources, and an `options` hash.
// The `options` hash respects two members: `language`, which specifies which
// Pygments lexer to use; and `comment_chars`, which specifies the comment
// characters of the target language. The options default to `'php'` and `'//'`,
// respectively.
class Docco
{
	public $version		= '0.1';
	public $options		= array(
		'language'		=> 'php',
		'comment_chars'	=> '//',
		'save_dir'		=> 'docs/'
	);

	// The filename as given to `Docco`
	public $file			= '';

	public $html_file		= '';

	// The name of the file, used as a title
	public $title			= '';

	public $data			= '';
	public $comment_pattern	= '';

	// An array representing each *section* of the source file. Each
	// item in the array has the form: `[docs_html, code_html]`, where both
	// elements are strings containing the documentation and source code HTML,
	// respectively.
	public $sections	= array();

	// A list of all source filenames included in the documentation set. Useful
	// for building an index of other files.
	public $sources		= array();

	public $render_html	= '';

	public function __construct($filename, $sources = array(), $options = array())
	{
		$this->file	= $filename;
		$this->title = basename($filename);
		$this->data = (file_exists($filename))?file_get_contents($filename):$this->data;
		$this->options = array_merge($this->options, $options);
		$this->sources = $sources;
		$this->comment_pattern = "@^\\s*".$this->options['comment_chars']."@";
		$this->sections = $this->highlight($this->split($this->parse($this->data)));
		$this->render_html = $this->to_html($this->sections[0],$this->sections[1]);
	}

	// Generate HTML output for the entire document.
	public function to_html($docs, $code)
	{
		return $this->render_template("layout.php",array(
			'docs'		=> $docs,
			'code'		=> $code,
			'title'		=> $this->title
		));
	}

	public function render_template($file, $vars = array())
	{
		$otag = '{{';
		$ctag = '}}';
		if(file_exists($file)) {
			ob_start();
			extract($vars);
			include($file);
			$contents = ob_get_contents();
			if (!empty($vars)) {
				foreach ($vars as $label => $value) {
					if (!is_array($value)) {
						$contents = str_replace($otag.$label.$ctag, $value, $contents);
					}
				}
			}
			ob_end_clean();
		} else {
			die("Template file does not exist: <code>".$file."</code>");
		}
		return $contents;
	}

	public function save($return_message = false)
	{
		$file = $this->options['save_dir'].basename($this->file,".php").'.html';
		$this->html_file = $file;
		$h = fopen($file, 'w') or die("Unable to open file in .".$this->options['save_dir']);
		fwrite($h, $this->render_html);
		fclose($h);
		chmod($file,0777);
		if ($return_message) {
			echo "<code>".basename($this->file,".php").'.html'."</code> was saved to <code>".dirname(__FILE__).'/'.$this->options['save_dir']."</code>";
		} else {
			return true;
		}
	}

	// ### Internal Parsing and Highlighting

	// Parse the raw file data into a list of two-tuples. Each tuple has the
	// form `[docs, code]` where both elements are arrays containing the
	// raw lines parsed from the input file. The first line is ignored if it
	// is a shebang line.
	public function parse()
	{
		$sections = array(); $docs = array(); $code = array();
		$lines = explode("\n",$this->data);
		if (preg_match("/^\#\!/",$lines[0]) || preg_match("/^\<\?php/",$lines[0])) {
			array_shift($lines);
		}
		if (preg_match("/^\?\>/",$lines[count($lines)-1])) {
			array_pop($lines);
		}
		foreach ($lines as $line) {
			if (preg_match($this->comment_pattern,$line)) {
				if (!empty($code)) {
					$sections[] = array($docs,$code);
					$docs = array();
					$code = array();
				}
				$docs[] = $line;
			} else {
				$code[] = $line;
			}
		}
		if (!empty($docs) || !empty($code)) {
			$sections[] = array($docs,$code);
		}
		return $sections;
	}

	// Take the list of paired *sections* two-tuples and split into two
	// separate lists: one holding the comments with leaders removed and
	// one with the code blocks.
	public function split($sections)
	{
		$docs_blocks = array(); $code_blocks = array();
		foreach ($sections as $section) {
			foreach ($section[0] as $key => $line) {
				$section[0][$key] = preg_replace($this->comment_pattern,"",$line);
			}
			$docs_blocks[] = join("\n",$section[0]);
			foreach ($section[1] as $key => $line) {
				if (preg_match_all("/^(\t+)/",$line,$match)) {
					$tabs = count(explode("\t",$match[1][0]));
					$section[1][$key] = preg_replace("/^\t+/",str_repeat("  ",$tabs),$line);
				}
			}
			$code_blocks[] = join("\n",$section[1]);
		}
		return array($docs_blocks, $code_blocks);
	}

	// Take the result of `split` and apply Markdown formatting to comments and
	// syntax highlighting to source code.
	public function highlight($blocks)
	{
		list($docs_blocks,$code_blocks) = $blocks;

		// Combine all docs blocks into a single big markdown document with section
		// dividers and run through the Markdown processor. Then split it back out
		// into separate sections.
		$docs_blocks = array_map('trim',$docs_blocks);
		$markdown = join("\n\n##### DIVIDER\n\n",$docs_blocks);
		$docs_html = preg_split("/\n*<h5>DIVIDER<\/h5>\n*/m",Markdown($markdown));

		// Combine all code blocks into a single big stream and run through either
		// `pygmentize(1)` or <http://pygments.appspot.com>
		$code_stream = join("\n\n".$this->options['comment_chars']." DIVIDER\n\n",$code_blocks);

		$code_html = $this->highlight_webservice($code_stream);

		// Do some post-processing on the pygments output to split things back
		// into sections, remove PHP opening and closing tags and remove partial `<pre>` blocks.
		$code_html = preg_split("@\n*<span class=\"c\">".$this->options['comment_chars']." DIVIDER<\/span>\n*@m",$code_html);

		foreach ($code_html as $key => $value) {
			$code_html[$key] = preg_replace(
				array(
					"/\n?<div class=\"highlight\"><pre>/m",
					"/\n?<\/pre><\/div>\n/m",
					"/\n?<span class=\"cp\">&lt;\?php<\/span>\n?/m",
					"/\n?<span class=\"cp\">\?&gt;<\/span><span class=\"x\"><\/span>/"),
				array('','','',''),
				$value
			);
		}
		return array($docs_html,$code_html);
	}

	public function highlight_webservice($code)
	{
		$post = array(
			'lang'	=> $this->options['language'],
			'code'	=> ($this->options['language'] == 'php')?"<?php".$code."?>":$code
		);
		$post_str = '';
		foreach($post as $key => $value) {
			$post_str .= $key.'='.urlencode($value).'&';
		}
		$post_str = substr($post_str, 0, -1);

		$curl = curl_init();
		curl_setopt($curl,CURLOPT_URL,'http://pygments.appspot.com/');
		curl_setopt($curl,CURLOPT_POST,TRUE);
		curl_setopt($curl,CURLOPT_POSTFIELDS,$post_str);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
		$result = curl_exec($curl);
		curl_close($curl);
		return $result;
	}
}
?>