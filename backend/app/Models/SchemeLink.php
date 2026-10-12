<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/** A post or event linked to a scheme; picking schemes saves only these links, so they publish the website themselves. */
class SchemeLink extends Pivot {}
