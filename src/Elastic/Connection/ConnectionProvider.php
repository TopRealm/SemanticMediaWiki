<?php

namespace SMW\Elastic\Connection;

use Elasticsearch\ClientBuilder;
use Psr\Log\LoggerAwareTrait;
use SMW\Connection\ConnectionProvider as IConnectionProvider;
use SMW\Elastic\Config;
use SMW\Elastic\Exception\ClientBuilderNotFoundException;
use SMW\Elastic\Exception\MissingEndpointConfigException;

/**
 * @private
 *
 * @license GPL-2.0-or-later
 * @since 3.0
 *
 * @author mwjames
 */
class ConnectionProvider implements IConnectionProvider {

	use LoggerAwareTrait;

	/**
	 * @var LockManager
	 */
	private $lockManager;

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * @var Client
	 */
	private $connection;

	/**
	 * @since 3.0
	 *
	 * @param LockManager $lockManager
	 * @param config $config
	 */
	public function __construct( LockManager $lockManager, Config $config ) {
		$this->lockManager = $lockManager;
		$this->config = $config;
	}

	/**
	 * @see ConnectionProvider::getConnection
	 *
	 * @since 3.0
	 *
	 * @return Client
	 */
	public function getConnection() {
		if ( $this->connection !== null ) {
			return $this->connection;
		}

		$endpoints = $this->config->safeGet( Config::ELASTIC_ENDPOINTS, [] );
		$clientBuilder = null;

		if ( !$this->hasEndpoints( $endpoints ) ) {
			throw new MissingEndpointConfigException();
		}

		$endpoints = array_map( [ $this, 'buildHostString' ], $endpoints );

		$params = [
			'hosts' => $endpoints,
			'retries' => $this->config->dotGet( 'connection.retries', 1 ),

			'connectionParams' => [
				'client' => [

					// controls the request timeout
					'timeout' => $this->config->dotGet( 'connection.timeout', 30 ),

					// controls the original connection timeout duration
					'connect_timeout' => $this->config->dotGet( 'connection.connect_timeout', 30 )
				]
			],

			// Use `singleHandler` if you know you will never need async capabilities,
			// since it will save a small amount of overhead by reducing indirection
			// 'handler' => ClientBuilder::singleHandler()
		];

		$authentication = $this->config->safeGet( Config::ELASTIC_CREDENTIALS );

		if ( $authentication ) {
			$user = $authentication['user'] ?? $authentication[0];
			$pass = $authentication['pass'] ?? $authentication[1];

			$params['basicAuthentication'] = [ $user, $pass ];
		}

		if ( $this->hasAvailableClientBuilder() ) {
			$clientBuilder = ClientBuilder::fromConfig( $params, true );
		}

		$this->connection = $this->newClient( $clientBuilder );

		$this->connection->setLogger(
			$this->logger
		);

		$this->logger->info(
			[ 'Connection', '{provider} : {hosts}' ],
			[ 'role' => 'developer', 'provider' => 'elastic', 'hosts' => $params['hosts'] ]
		);

		return $this->connection;
	}

	/**
	 * @see ConnectionProvider::releaseConnection
	 *
	 * @since 3.0
	 */
	public function releaseConnection() {
		$this->connection = null;
	}

	private function newClient( $clientBuilder = null ) {
		if ( $clientBuilder === null ) {
			return new DummyClient();
		}

		// For unit/integration tests use a special `TestClient` to force a refresh
		// hereby make results immediately available on some actions before
		// the actual request is transmitted to the `Client`
		if ( defined( 'MW_PHPUNIT_TEST' ) ) {
			return new TestClient( $clientBuilder, $this->lockManager, $this->config );
		}

		return new Client( $clientBuilder, $this->lockManager, $this->config );
	}

	private function hasEndpoints( $endpoints ) {
		if ( $this->config->isDefaultStore() === false ) {
			return true;
		}

		return $endpoints !== [];
	}

	private function hasAvailableClientBuilder() {
		if ( $this->config->isDefaultStore() === false ) {
			return false;
		}

		// Fail hard because someone selected the ElasticStore but forgot to install
		// the elastic interface!
		if ( !class_exists( 'Elasticsearch\ClientBuilder' ) ) {
			throw new ClientBuilderNotFoundException();
		}

		return true;
	}

	/**
	 * ElasticSearch client 8.0 no longer supports the associative array syntax for hosts. This function acts as a
	 * B/C adapter, that transform a host in the old syntax to a host in the new syntax.
	 *
	 * @see https://github.com/elastic/elasticsearch-php/issues/1214
	 *
	 * @param array|string $host
	 * @return string
	 */
	private function buildHostString( $host ): string {
		if ( is_string( $host ) ) {
			return $host;
		}

		$scheme = $host['scheme'] ?? 'https';
		$hostName = $host['host'] ?? 'localhost';
		$port = $host['port'] ?? 9200;

		return $scheme . '://' . $hostName . ':' . $port;
	}

}
