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

namespace Adshares\Adserver\Http;

use DateTime;
use Doctrine\Common\Cache\FilesystemCache;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use function is_string;

/**
 * Various helpful methods.
 */
class Utils
{
    // not yet reviewed
    // TODO: remove $container

    const VALUE_GLUE = "\t";

    const PROP_GLUE = "\r";

    const ZONE_GLUE = "\n";

    const VALUE_MIN = "\x00";

    const VALUE_MAX = "\xFF";

    const NUMERIC_PAD_FORMAT = "%'08.2f";

    public const ENV_DEV = 'local';

    public const ENV_PROD = 'production';

    public static function getImpressionContext(Request $request, $contextStr = null)
    {
        $contextStr = $contextStr ?: $request->query->get('ctx');
        if ($contextStr) {
            if (is_string($contextStr)) {
                $context = self::decodeZones($contextStr);
            } else {
                $context = ['page' => $contextStr];
            }
        } else {
            $context = null;
        }

        return [
            'site' => self::getSiteContext($request, $context),
            'device' => [
                'ua' => $request->userAgent(),
                'ip' => $request->ip(),
                'ips' => $request->ips(),
                'headers' => $request->headers->all(),
            ],
        ];
    }

    public static function decodeZones($zonesStr)
    {
        $zonesStr = self::urlSafeBase64Decode($zonesStr);

        $zones = explode(self::ZONE_GLUE, $zonesStr);
        $fields = explode(self::VALUE_GLUE, array_shift($zones));
        //         return $fields;
        $data = [];

        foreach ($zones as $zoneStr) {
            $zone = [];
            $propStrs = explode(self::PROP_GLUE, $zoneStr);
            foreach ($propStrs as $propStr) {
                $prop = explode(self::VALUE_GLUE, $propStr);
                $zone[$fields[$prop[0]]] = is_numeric($prop[1]) ? floatval($prop[1]) : $prop[1];
            }
            $data[] = $zone;
        }

        $result = [
            'page' => array_shift($data),
        ];
        if ($data) {
            $result['zones'] = $data;
        }

        return $result;
    }

    public static function urlSafeBase64Decode($string)
    {
        return base64_decode(
            str_replace(
                [
                    '_',
                    '-',
                ],
                [
                    '/',
                    '+',
                ],
                $string
            )
        );
    }

    public static function getSiteContext(Request $request, $context)
    {
        $site = [];

        if (empty($context) || !isset($context['page'])) {
            return $site;
        }

        $page = $context['page'];

        if (!$page['url']) {
            $page['url'] = $request->headers->get('Referer');
        }
        $url = parse_url($page['url']);
        $site['domain'] = $url['host'] ?? null;
        $site['inframe'] = isset($page['frame']) ? ($page['frame'] ? 'yes' : 'no') : null;
        $site['page'] = $page['url'];
        if (isset($page['keywords']) && is_string($page['keywords'])) {
            $site['keywords'] = explode(',', $page['keywords']);
            foreach ($site['keywords'] as &$word) {
                $word = strtolower(trim($word));
            }
        }

        return $site;
    }

    public static function getDeviceContext(Request $request, $context)
    {
        $device = [];
        if ($context && isset($context['page'])) {
            $page = $context['page'];

            $device['w'] = $page['width'] ?? null;
            $device['h'] = $page['height'] ?? null;
        }

        $logger = LoggerHelper::createDefaultLogger(new NullOutput());

        $fileCache = new FilesystemCache(storage_path('framework/cache/browscap'));
        $cache = new SimpleCacheAdapter($fileCache);

        $browscap = new \BrowscapPHP\Browscap($cache, $logger);

        $browser = $browscap->getBrowser();

        $locale = locale_accept_from_http($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en');
        if ($locale) {
            $device['language'] = $locale;
        }

        $device['browser'] = $browser->browser;
        $device['browserv'] = $browser->version > 0 ? $browser->version : null;

        $device['input'] = $browser->device_pointing_method ?? null;

        $device['os'] = $browser->platform;

        if (is_numeric($browser->platform_version)) {
            $device['osv'] = $browser->platform_version;
        } elseif (preg_match('/(.*?)([0-9\.]+)/', $browser->platform, $match)) {
            $device['os'] = $match[1];
            $device['osv'] = $match[2];
        } else {
            $device['osv'] = $browser->platform;
        }

        if (true === $browser->ismobiledevice) {
            $device['type'] = 'mobile';
        } elseif (true === $browser->istablet) {
            $device['type'] = 'tablet';
        } elseif (true === $browser->issyndicationreader) {
            $device['type'] = 'syndicationreader';
        } elseif (true === $browser->crawler) {
            $device['type'] = 'crawler';
        } elseif (true === $browser->isfake) {
            $device['type'] = 'fake';
        } else {
            $device['type'] = 'desktop';
        }

        foreach ($device as $key => &$value) {
            if ('false' === $value || 'unknown' === $value || null === $value || false === $value) {
                unset($device[$key]);
            }
            if (is_string($value)) {
                $value = strtolower($value);
            }
        }

        $geo = self::getGeoData('128.65.210.8');
        if ($geo) {
            $device['geo'] = $geo;
        }

        return $device;
    }

    private static function getGeoData($clientIp)
    {
        $geo = [];
        if (function_exists('geoip_record_by_name')) {
            @$data = \geoip_record_by_name($clientIp);
            if ($data) {
                foreach ($data as $key => $value) {
                    if ($value) {
                        $geo[$key] = $value;
                    }
                }
            } else {
                @$data = \geoip_country_code_by_name($clientIp);
                if ($data) {
                    $geo['country_code'] = $data;
                }
            }
        }

        return $geo;
    }

    public static function addUrlParameter($url, $name, $value)
    {
        $param = $name.'='.urlencode($value);
        $qPos = strpos($url, '?');
        if (false == $qPos) {
            return $url.'?'.$param;
        } elseif ($qPos == strlen($url) - 1) {
            return $url.$param;
        } else {
            return $url.'&'.$param;
        }
    }

    public static function getRawTrackingId($encodedId)
    {
        if (!$encodedId) {
            return '';
        }
        $input = self::urlSafeBase64Decode($encodedId);

        return bin2hex(substr($input, 0, 16));
    }

    public static function attachOrProlongTrackingCookie(
        $secret,
        Request $request,
        Response $response,
        $contentSha1,
        DateTime $contentModified,
        ?string $impressionId = null
    ): string {
        $tid = $request->cookies->get('tid');
        if (!self::validTrackingId($tid, $secret)) {
            $tid = null;
            $etags = $request->getETags();

            if (isset($etags[0])) {
                $tag = str_replace('"', '', $etags[0]);
                $tid = self::decodeEtag($tag);
            }

            if ($tid === null || !self::validTrackingId($tid, $secret)) {
                $tid = self::createTrackingId($secret, $impressionId);
            }
        }
        $response->headers->setCookie(
            new Cookie(
                'tid',
                $tid,
                new DateTime('+ 1 month'),
                '/',
                $request->getHost()
            )
        );
        $response->headers->set('P3P', 'CP="CAO PSA OUR"'); // IE needs this, not sure about meaning of this header

        $response->setCache(
            [
                'etag' => self::generateEtag($tid, $contentSha1),
                'last_modified' => $contentModified,
                'max_age' => 0,
                'private' => true,
            ]
        );
        $response->headers->addCacheControlDirective('no-transform');

        return $tid;
    }

    public static function validTrackingId($input, $secret): bool
    {
        if (!\is_string($input)) {
            return false;
        }
        $input = self::urlSafeBase64Decode($input);
        $id = substr($input, 0, 16);
        $checksum = substr($input, 16);

        return strpos(sha1($id.$secret, true), $checksum) === 0;
    }

    private static function decodeEtag($etag)
    {
        $etag = str_replace('"', '', $etag);

        return self::urlSafeBase64Encode(strrev(substr(self::urlSafeBase64Decode($etag), 6)));
    }

    public static function urlSafeBase64Encode($string): string
    {
        return str_replace(
            [
                '/',
                '+',
                '=',
            ],
            [
                '_',
                '-',
                '',
            ],
            base64_encode($string)
        );
    }

    public static function createTrackingId(string $secret, ?string $impressionId = null): string
    {
        $input = [];

        if ($impressionId !== null) {
            $input[] = $impressionId;
            $input[] = $_SERVER['REMOTE_ADDR'] ?? '';
        } else {
            $input[] = microtime();
            $input[] = $_SERVER['REMOTE_ADDR'] ?? mt_rand();
            $input[] = $_SERVER['REMOTE_PORT'] ?? mt_rand();
            $input[] = $_SERVER['REQUEST_TIME_FLOAT'] ?? mt_rand();
            $input[] = is_callable('random_bytes') ? random_bytes(22) : openssl_random_pseudo_bytes(22);
        }

        $id = substr(sha1(implode(':', $input), true), 0, 16);
        $checksum = substr(sha1($id.$secret, true), 0, 6);

        return self::urlSafeBase64Encode($id.$checksum);
    }

    private static function generateEtag($tid, $contentSha1): string
    {
        $sha1 = pack('H*', $contentSha1);

        return self::urlSafeBase64Encode(substr($sha1, 0, 6).strrev(self::urlSafeBase64Decode($tid)));
    }

    public static function flattenKeywords(array $keywords, $prefix = ''): array
    {
        $ret = [];

        if (array_values($keywords) == $keywords) {
            $keywords = array_flip($keywords);
            foreach ($keywords as &$value) {
                $value = 1;
            }
        }

        foreach ($keywords as $keyword => $value) {
            if (is_array($value)) {
                $ret = array_merge($ret, self::flattenKeywords($value, $keyword.'_'));
            } else {
                $ret[$prefix.$keyword] = $value;
            }
        }

        return $ret;
    }

    public static function generalKeywordMatch(array $keywords, $name, $min, $max)
    {
        $path = explode('_', $name);
        $last = array_pop($path);
        $keys = explode(':', $last);
        $values = [];
        foreach ($keys as $key) {
            $key = implode('_', $path).'_'.$key;
            if (isset($keywords[$key])) {
                $values[] = is_array($keywords[$key]) ? $keywords[$key] : [$keywords[$key]];
            } else {
                return false;
            }
        }
        $vectors = [''];
        for ($i = 0; $i < count($values); ++$i) {
            $orgVectors = $vectors;
            for ($j = 0; $j < count($values[$i]); ++$j) {
                $newVector = [];
                foreach ($orgVectors as $vector) {
                    $val = $values[$i][$j];
                    if (is_numeric($val)) {
                        $val = sprintf(self::NUMERIC_PAD_FORMAT, $val);
                    }
                    $newVector[] = ($vector ? $vector.':' : '').$val;
                }
                if (0 == $j) {
                    $vectors = $newVector;
                } else {
                    $vectors = array_merge($vectors, $newVector);
                }
            }
        }

        foreach ($vectors as $vector) {
            if (strcmp($min, $vector) <= 0 && strcmp($vector, $max) <= 0) {
                return true;
            }
        }

        return false;
    }

    public static function createCaseIdContainsEventType(string $baseCaseId, string $eventType): string
    {
        $caseId = substr($baseCaseId, 0, -2);

        if ($eventType === 'request') {
            return $caseId.'01';
        }

        if ($eventType === 'view') {
            return $caseId.'02';
        }

        if ($eventType === 'click') {
            return $caseId.'03';
        }

        throw new \RuntimeException(sprintf('Invalid event type %s for case id %s', $eventType, $baseCaseId));
    }
}
