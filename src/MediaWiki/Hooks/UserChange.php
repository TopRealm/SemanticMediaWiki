<?php

namespace SMW\MediaWiki\Hooks;

use MediaWiki\User\UserIdentity;
use SMW\MediaWiki\HookListener;
use SMW\MediaWiki\Jobs\UpdateJob;
use SMW\NamespaceExaminer;
use SMW\Services\ServicesFactory as ApplicationFactory;
use Title;

/**
 * @see https://www.mediawiki.org/wiki/Manual:Hooks/BlockIpComplete
 * @see https://www.mediawiki.org/wiki/Manual:Hooks/UnblockUserComplete
 * @see https://www.mediawiki.org/wiki/Manual:Hooks/UserGroupsChanged
 *
 * Act on events that happen outside of the normal parser process to ensure that
 * changes to pre-defined properties related to a user status can be invoked.
 *
 * @license GPL-2.0-or-later
 * @since 3.0
 *
 * @author mwjames
 */
class UserChange implements HookListener {

	/**
	 * @var NamespaceExaminer
	 */
	private $namespaceExaminer;

	/**
	 * @var string
	 */
	private $origin = '';

	/**
	 * @since 3.0
	 *
	 * @param NamespaceExaminer $namespaceExaminer
	 */
	public function __construct( NamespaceExaminer $namespaceExaminer ) {
		$this->namespaceExaminer = $namespaceExaminer;
	}

	/**
	 * @since 3.0
	 *
	 * @param string $origin
	 */
	public function setOrigin( $origin ) {
		$this->origin = $origin;
	}

	/**
	 * @since 3.0
	 *
	 * @param UserIdentity|string|null $user
	 */
	public function process( $user ) {
		if ( !$this->namespaceExaminer->isSemanticEnabled( NS_USER ) ) {
			return false;
		}

		// getTargetUserIdentity returns null if it is not user(eg. CIDR)
		// https://github.com/SemanticMediaWiki/SemanticMediaWiki/issues/5263
		if ( $user === null ) {
			return false;
		}

		if ( $user instanceof UserIdentity ) {
			$user = $user->getName();
		}

		$updateJob = ApplicationFactory::getInstance()->newJobFactory()->newUpdateJob(
			Title::newFromText( $user, NS_USER ),
			[
				UpdateJob::FORCED_UPDATE => true,
				'origin' => $this->origin
			]
		);

		$updateJob->lazyPush();

		return true;
	}

}
