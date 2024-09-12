<?php

namespace Drupal\tide_logs\Logger;

use GuzzleHttp\Client;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LogMessageParserInterface;
use Drupal\tide_logs\Logger\TideSectionIoIdService;

/**
 * Defines a logger factory for the SumoLogic channel.
 */
class TideLogsLoggerFactory {

  /**
   * Creates an instance of the SumoLogic logger.
   *
   * @param ConfigFactoryInterface $config
   *   The config service.
   * @param LogMessageParserInterface $parser
   *   The log message parser service.
   * @param Client $http_client
   *   The http client service.
   * @param TideSectionIoIdService $tide_section_io_id_service
   *   The service to retrieve the x-section-io-id.
   *
   * @return TideLogsLogger
   *   The logger instance that was created.
   */
  public static function create(
    ConfigFactoryInterface $config,
    LogMessageParserInterface $parser,
    Client $http_client,
    TideSectionIoIdService $tide_section_io_id_service
  ) {
    return new TideLogsLogger(
      $parser,
      $http_client,
      $config->get('tide_logs.settings'),
      $tide_section_io_id_service // Add this service to the logger
    );
  }

}
