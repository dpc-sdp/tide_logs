<?php

namespace Drupal\tide_logs\Logger;

use Symfony\Component\HttpFoundation\RequestStack;

class TideSectionIoIdService {

  protected $requestStack;

  public function __construct(RequestStack $requestStack) {
    $this->requestStack = $requestStack;
  }

  // Get the x-section-io-id from the current request's headers.
  public function getSectionIoId() {
    $request = $this->requestStack->getCurrentRequest();
    return $request ? $request->headers->get('x-section-io-id') : NULL;
  }
}
