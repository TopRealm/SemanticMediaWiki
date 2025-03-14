<?php

namespace SMW\Tests\SPARQLStore\QueryEngine;

use SMW\SPARQLStore\QueryEngine\DescriptionInterpreterFactory;

/**
 * @covers \SMW\SPARQLStore\QueryEngine\DescriptionInterpreterFactory
 * @group semantic-mediawiki
 *
 * @license GPL-2.0-or-later
 * @since 2.5
 *
 * @author mwjames
 */
class DescriptionInterpreterFactoryTest extends \PHPUnit\Framework\TestCase {

	public function testCanConstruct() {
		$this->assertInstanceOf(
			'\SMW\SPARQLStore\QueryEngine\DescriptionInterpreterFactory',
			new DescriptionInterpreterFactory()
		);
	}

	public function testCanConstructDispatchingDescriptionInterpreter() {
		$conditionBuilder = $this->getMockBuilder( '\SMW\SPARQLStore\QueryEngine\ConditionBuilder' )
			->disableOriginalConstructor()
			->getMock();

		$instance = new DescriptionInterpreterFactory();

		$this->assertInstanceOf(
			'\SMW\SPARQLStore\QueryEngine\DescriptionInterpreters\DispatchingDescriptionInterpreter',
			$instance->newDispatchingDescriptionInterpreter( $conditionBuilder )
		);
	}

}
