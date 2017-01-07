<?php

namespace Dorgflow\Service;

use Dorgflow\Waypoint\MasterBranch;

// needs:
// - git executor
// - situation ERM?
class WaypointManager {

  function __construct($git_info, $git_log, $git_executor) {
    $this->git_info = $git_info;
    $this->git_log = $git_log;
    $this->git_executor = $git_executor;
  }

  public function getMasterBranch() {
    if (empty($this->masterBranch)) {
      $this->masterBranch = new MasterBranch(
        $this->git_info,
        $this->git_log,
        $this->git_executor
      );
    }

    return $this->masterBranch;
  }

  public function getFeatureBranch() {

  }

  public function setUpPatches() {

  }

}
