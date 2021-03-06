<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

use Adshares\Adserver\Models\NetworkHost;
use Illuminate\Database\Seeder;

class MockDataNetworkHostsSeeder extends Seeder
{
    public function run()
    {
        $this->command->info('[mock] seeding: network_hosts');

        $networkHost = new NetworkHost();
        $networkHost->address = '0001-00000001-0001';
        $networkHost->host = 'http://webserver';
        $networkHost->last_broadcast = new DateTime();

        $networkHost->save();
    }
}
