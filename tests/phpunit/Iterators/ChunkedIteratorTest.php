<?php

namespace SMW\Tests\Iterators;

use SMW\Iterators\ChunkedIterator;
use SMW\Tests\PHPUnitCompat;

/**
 * @covers \SMW\Iterators\ChunkedIterator
 * @group semantic-mediawiki
 *
 * @license GPL-2.0-or-later
 * @since 3.0
 *
 * @author mwjames
 */
class ChunkedIteratorTest extends \PHPUnit\Framework\TestCase {

	use PHPUnitCompat;

	public function testCanConstruct() {
		$this->assertInstanceOf(
			ChunkedIterator::class,
			new ChunkedIterator( [] )
		);
	}

	public function testChunkedOnArray() {
		$result = [
			1, 42, 1001, 9999
		];

		$instance = new ChunkedIterator( $result, 2 );

		foreach ( $instance as $chunk ) {
			$this->assertCount(
				2,
				$chunk
			);
		}

		$chunks = iterator_to_array( $instance, false );

		$this->assertEquals(
			[ 1, 42 ],
			$chunks[0]
		);

		$this->assertEquals(
			[ 1001, 9999 ],
			$chunks[1]
		);
	}

	public function testInvalidConstructorArgumentThrowsException() {
		$this->expectException( 'RuntimeException' );
		$instance = new ChunkedIterator( 2 );
	}

	public function testInvalidChunkSizeArgumentThrowsException() {
		$this->expectException( 'InvalidArgumentException' );
		$instance = new ChunkedIterator( [], -1 );
	}

}
