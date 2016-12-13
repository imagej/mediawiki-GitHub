<?php

namespace GitHub;

use FileFetcher\FileFetcher;
use FileFetcher\FileFetchingException;
use Michelf\Markdown;
use ParamProcessor\ProcessingResult;
use Parser;
use ParserHooks\HookHandler;

/**
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class GitHubParserHook implements HookHandler {

	private $fileFetcher;
	private $gitHubUrl;

	private $fileName;
	private $repoName;
	private $branchName;
	private $lang;

	/**
	 * @param FileFetcher $fileFetcher
	 * @param string $gitHubUrl
	 */
	public function __construct( FileFetcher $fileFetcher, $gitHubUrl ) {
		$this->fileFetcher = $fileFetcher;
		$this->gitHubUrl = $gitHubUrl;
	}

	public function handle( Parser $parser, ProcessingResult $result ) {
		$this->setFields( $result );

		return $this->getRenderedContent($parser);
	}

	private function setFields( ProcessingResult $result ) {
		$params = $result->getParameters();

		$this->fileName = $params['file']->getValue();
		$this->repoName = $params['repo']->getValue();
		$this->branchName = $params['branch']->getValue();
		$this->lang = $params['lang']->getValue();
	}

	private function getRenderedContent( Parser $parser ) {
		$content = $this->getFileContent();

		if ( $this->isMarkdownFile() ) {
			$content = $this->renderAsMarkdown( $content );
		}
		else {
			$lang = $this->getLang();
			$content = "<source lang=\"$lang\">$content</source>";
			$content = $parser->recursiveTagParse( $content, null );
		}

		return $content;
	}

	private function getFileContent() {
		try {
			return $this->fileFetcher->fetchFile( $this->getFileUrl() );
		}
		catch ( FileFetchingException $ex ) {
			return '';
		}
	}

	private function getFileUrl() {
		return sprintf(
			'%s/%s/%s/%s',
			$this->gitHubUrl,
			$this->repoName,
			$this->branchName,
			$this->fileName
		);
	}

	private function getLang() {
		if ( strcmp($this->lang, 'auto') != 0 ) return $this->lang;
		// auto-detect by file extension
		$fileExt = pathinfo($this->fileName, PATHINFO_EXTENSION);
		switch ($fileExt) {
		case "py":
			return "python";
			break;
		default:
			// use extension as-is
			return $fileExt;
		}
	}

	private function isMarkdownFile() {
		return $this->fileHasExtension( 'md' ) || $this->fileHasExtension( 'markdown' );
	}

	private function fileHasExtension( $extension ) {
		$fullExtension = '.' . $extension;
		return substr( $this->fileName, -strlen( $fullExtension ) ) === $fullExtension;
	}

	private function renderAsMarkdown( $content ) {
		return Markdown::defaultTransform( $content );
	}

}
