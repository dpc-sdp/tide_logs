<?php

namespace Drupal\tide_logs\Logger;

use Drupal;
use Exception;
use Monolog\Logger;
use GuzzleHttp\Client;
use Monolog\Handler\SocketHandler;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\lagoon_logs\LagoonLogsLogProcessor;
use Drupal\lagoon_logs\Logger\LagoonLogsLogger;
use Drupal\Core\Logger\LogMessageParserInterface;
use Drupal\lagoon_logs\Logger\LagoonLogsLoggerFactory;

/**
 * Defines a logger channel for sending logs to SumoLogic.
 */
class TideLogsLogger extends LagoonLogsLogger {

  /**
   * Default channel name for MonoLog.
   */
  public const MONOLOG_CHANNEL_NAME = 'TideLogs';

  /**
   * Default udplog host.
   */
  public const DEFAULT_UDPLOG_HOST = 'logger.default.svc';

  /**
   * Default udplog port.
   */
  public const DEFAULT_UDPLOG_port = 5514;

  /**
   * Default SumoLogic category.
   */
  public const DEFAULT_CATEGORY = 'sdp/dev/tide';

  protected Client $httpClient;

  protected ImmutableConfig $moduleConfig;

  /**
   * Flag to indicate whether to print debug messages.
   *
   * @var boolean
   */
  protected bool $showDebug;

  /**
   * Constructs a TideLogsLogger object.
   *
   * @param LogMessageParserInterface $parser
   *   The log message parser service.
   * @param Client $http_client
   *   The http client service.
   * @param ImmutableConfig $module_config
   *   The module's config.
   */
  public function __construct(
    LogMessageParserInterface $parser,
    Client $http_client,
    $module_config
  ) {
    $this->parser = $parser;
    $this->httpClient = $http_client;
    $this->moduleConfig = $module_config;
    $this->hostName = $module_config->get('host') ?: static::DEFAULT_UDPLOG_HOST;
    $this->hostPort = $module_config->get('port') ?: static::DEFAULT_UDPLOG_port;
    $this->showDebug = (bool) $module_config->get('debug');
  }

  /**
   * {@inheritdoc}
   */
  public function log($level, $message, array $context = []): void {
    $sumoLogicHost = $this->getSumoLogicHost();
    $sumoLogicCategory = $this->getSumoLogicCategory();

    if ($this->showDebug) {
      Drupal::messenger()->addMessage(t(
        'Code: @code; Cat: @cat',
        [
          '@cat' => $sumoLogicCategory,
        ]
      ));
    }

    if (empty($sumoLogicHost) || empty($this->hostName) || empty($this->hostPort)) {
      return;
    }

    global $base_url;

    $logger = new Logger(
      !empty($context['channel']) ? $context['channel'] : self::MONOLOG_CHANNEL_NAME
    );

    $connectionString = sprintf(
      "udp://%s:%s",
      $this->hostName,
      $this->hostPort
    );
    $udpHandler = new SocketHandler($connectionString);

    $udpHandler->setChunkSize(self::LAGOON_LOGS_DEFAULT_CHUNK_SIZE_BYTES);

    $udpHandler->setFormatter(
      new TideLogsFormatter($sumoLogicHost, $sumoLogicCategory)
    );

    $logger->pushHandler($udpHandler);

    $message_placeholders = $this->parser->parseMessagePlaceholders(
      $message,
      $context
    );
    $message = strip_tags(
      empty($message_placeholders) ? $message : strtr(
        $message,
        $message_placeholders
      )
    );

    $processorData = $this->transformDataForProcessor(
      $level,
      $message,
      $context,
      $base_url
    );

    $logger->pushProcessor(new LagoonLogsLogProcessor($processorData));

    try {
      $logger->log($this->mapRFCtoMonologLevels($level), $message);
    } catch (Exception $exception) {
      if ($this->showDebug) {
        Drupal::messenger()->addMessage(t(
          'Error logging to SumoLogic: @error',
          ['@error' => $exception->getMessage()]
        ));
      }
    }
  }

  /**
   * Determines the host for the log payload.
   *
   * Since it uses the LAGOON_PROJECT & LAGOON_GIT_SAFE_BRANCH variables, this
   * will effectively correspond to the site's kubernetes namespace.
   *
   * @return string|boolean
   *   Either the namespace, or False in case logging is not enabled.
   */
  public function getSumoLogicHost() {
    $enabled = $this->moduleConfig->get('enable');
    return $enabled ?
      implode('-', [
        getenv('LAGOON_PROJECT') ?: LagoonLogsLoggerFactory::LAGOON_LOGS_DEFAULT_LAGOON_PROJECT,
        getenv('LAGOON_GIT_SAFE_BRANCH') ?: LagoonLogsLoggerFactory::LAGOON_LOGS_DEFAULT_SAFE_BRANCH,
      ]) :
      FALSE;
  }

  /**
   * Determines the SumoLogic Category to be used as header when sending logs.
   *
   * The category can be specified either in settings or as an environment
   * variable, the latter taking precedence when there are conflicts.
   *
   * @return string
   *   Either the specified category or the default.
   */
  public function getSumoLogicCategory() {
    $enabled = $this->moduleConfig->get('enable');
    if (!$enabled) {
      return FALSE;
    }
    // Allow category to be specified via environment.
    $category = getenv('SUMOLOGIC_CATEGORY');
    if (!$category) {
      $category = $this->moduleConfig->get('sumologic_category');
    }
    return $category ?: static::DEFAULT_CATEGORY;
  }

}
