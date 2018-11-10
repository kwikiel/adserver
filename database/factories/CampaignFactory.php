<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

use Adshares\Adserver\Models\Campaign;
use Faker\Generator as Faker;

$factory->define(Campaign::class, function (Faker $faker) {
    $startEnd = $faker->dateTimeThisMonth();
    $startTime = $faker->dateTimeThisMonth($startEnd);
    return [
        'landing_url' => $faker->url(),
        'time_start' => $startTime,
        'time_end' => $startEnd,
        'status' => '0',
        'name' => $faker->word(),
        'max_cpc' => '2',
        'max_cpm' => '1',
        'budget' => '100',
        'targeting_excludes' => [],
        'targeting_requires' => [],
        'classification_status' => 0,
        'classification_tags' => null,
    ];
});