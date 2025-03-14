<?php

namespace Onoi\Tesa\Tokenizer;

/**
 * @license GPL-2.0-or-later
 * @since 0.1
 *
 * @author mwjames
 */
class PunctuationRegExTokenizer implements Tokenizer {

	/**
	 * @var Tokenizer
	 */
	private $tokenizer;

	/**
	 * @var string
	 */
	private $patternExemption = '';

	/**
	 * @since 0.1
	 *
	 * @param Tokenizer|null $tokenizer
	 */
	public function __construct( ?Tokenizer $tokenizer = null ) {
		$this->tokenizer = $tokenizer;
	}

	/**
	 * @since 0.1
	 *
	 * {@inheritDoc}
	 */
	public function setOption( $name, $value ) {
		if ( $this->tokenizer !== null ) {
			$this->tokenizer->setOption( $name, $value );
		}

		if ( $name === self::REGEX_EXEMPTION ) {
			$this->patternExemption = $value;
		}
	}

	/**
	 * @since 0.1
	 *
	 * {@inheritDoc}
	 */
	public function isWordTokenizer() {
		return $this->tokenizer !== null ? $this->tokenizer->isWordTokenizer() : true;
	}

	/**
	 * @since 0.1
	 *
	 * @param string $string
	 *
	 * @return array|false
	 */
	public function tokenize( $string ) {
		if ( $this->tokenizer !== null ) {
			$string = implode( " ", $this->tokenizer->tokenize( $string ) );
		}

		$pattern = str_replace(
			$this->patternExemption,
			'',
			'＿－・，、；：！？．。…◆★◇□■（）【】《》〈〉；：“”＂〃＇｀［］｛｝｢｣＠＊＼／＆＃％｀＾＋＜＝＞｜～≪≫─＄＂_\-･,､;:!?.｡()[\]{}「」@*\/&#%`^+<=>|~«»$"\s'
		);

		$result = preg_split( '/[' . $pattern . ']+/u', $string, -1, PREG_SPLIT_NO_EMPTY );

		if ( $result === false ) {
			$result = [];
		}

		return $result;
	}

}
