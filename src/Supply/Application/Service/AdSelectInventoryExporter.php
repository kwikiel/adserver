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

namespace Adshares\Supply\Application\Service;

use Adshares\Supply\Application\Service\Exception\NoBannersForGivenCampaign;
use Adshares\Supply\Domain\Model\Campaign;
use Adshares\Supply\Domain\Model\CampaignCollection;

class AdSelectInventoryExporter
{
    private $client;

    public function __construct(AdSelect $client)
    {
        $this->client = $client;
    }

    public function export(?CampaignCollection $campaignsToAddOrUpdate, ?CampaignCollection $campaignsToDelete): void
    {
        if ($campaignsToAddOrUpdate) {
            /** @var Campaign $campaign */
            foreach ($campaignsToAddOrUpdate as $campaign) {
                $this->client->exportInventory($campaign);
            }
        }

        if ($campaignsToDelete) {
            $this->client->deleteFromInventory($campaignsToDelete);
        }
    }
}
