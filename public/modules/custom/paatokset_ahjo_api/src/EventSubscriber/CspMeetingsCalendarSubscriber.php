<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\EventSubscriber;

use Drupal\csp\Csp;
use Drupal\helfi_platform_config\EventSubscriber\CspSubscriberBase;

/**
 * Event subscriber for CSP policy alteration.
 *
 * CSP directives for meetings calendar vue.js-application.
 *
 * @package Drupal\paatokset_ahjo_api\EventSubscriber
 */
class CspMeetingsCalendarSubscriber extends CspSubscriberBase {

  const SCRIPT_SRC = [Csp::POLICY_UNSAFE_EVAL];

}
