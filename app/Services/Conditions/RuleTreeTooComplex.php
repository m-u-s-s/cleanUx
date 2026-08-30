<?php

namespace App\Services\Conditions;

use RuntimeException;

/** Un arbre trop profond ou trop large : refuse, et non silencieusement vide. */
class RuleTreeTooComplex extends RuntimeException {}
