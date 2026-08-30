<?php

namespace App\Services\Automation;

use RuntimeException;

/** Une regle qui n'a jamais rien observe ne peut pas etre armee. */
class ArmementRefuse extends RuntimeException {}
