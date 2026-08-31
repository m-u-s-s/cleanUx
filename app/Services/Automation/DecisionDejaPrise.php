<?php

namespace App\Services\Automation;

use RuntimeException;

/** Une proposition tranchee (validee, refusee, echouee, expiree) ne se redecide jamais. */
class DecisionDejaPrise extends RuntimeException {}
