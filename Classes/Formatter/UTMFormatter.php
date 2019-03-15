<?php
declare(strict_types=1);

namespace Brotkrueml\BytCoordconverter\Formatter;

use Brotkrueml\BytCoordconverter\Domain\Model\CoordinateConverterParameter;

/**
 * This file is part of the "byt_coordconverter" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */
class UTMFormatter implements FormatterInterface
{
    /**
     * The major (equitorial) axis
     *
     * @var float
     */
    const ELLIPSOID_MAJOR_AXIS = 6378137.0;


    /**
     * The minor (equitorial) axis
     *
     * @var float
     */
    const ELLIPSOID_MINOR_AXIS = 6356752.314;


    /**
     * The scaling factor
     *
     * @var float
     */
    const SCALING_FACTOR = 0.9996;


    public function format(CoordinateConverterParameter $parameter): string
    {
        return static::getUtmFromLatitudeLongitude(
            $parameter->getLatitude(),
            $parameter->getLongitude()
        );
    }

    private static function getUtmFromLatitudeLongitude(float $latitude, float $longitude): string
    {
        $eccentricity = static::getEccentricityOfReferenceEllipsoid();

        $latitudeRad = $latitudeRad = $latitude * (pi() / 180.0);
        $longitudeRad = $longitude * (pi() / 180.0);

        $longitudinalZone = static::getLongitudinalZone($latitude, $longitude);

        $longitudeOrigin = ($longitudinalZone - 1) * 6 - 180 + 3;
        $longitudeOriginRad = $longitudeOrigin * (pi() / 180.0);

        $utmZone = static::getLatitudinalZone($latitude);

        $eccentricityPrimeSquared = ($eccentricity) / (1 - $eccentricity);

        $n = static::ELLIPSOID_MAJOR_AXIS / sqrt(1 - $eccentricity * sin($latitudeRad) * sin($latitudeRad));
        $t = tan($latitudeRad) * tan($latitudeRad);
        $c = $eccentricityPrimeSquared * cos($latitudeRad) * cos($latitudeRad);
        $A = cos($latitudeRad) * ($longitudeRad - $longitudeOriginRad);

        $M =
            static::ELLIPSOID_MAJOR_AXIS
            * ((1
                    - $eccentricity / 4
                    - 3 * $eccentricity * $eccentricity / 64
                    - 5 * $eccentricity * $eccentricity * $eccentricity / 256)
                * $latitudeRad
                - (3 * $eccentricity / 8
                    + 3 * $eccentricity * $eccentricity / 32
                    + 45 * $eccentricity * $eccentricity * $eccentricity / 1024)
                * sin(2 * $latitudeRad)
                + (15 * $eccentricity * $eccentricity / 256
                    + 45 * $eccentricity * $eccentricity * $eccentricity / 1024)
                * sin(4 * $latitudeRad)
                - (35 * $eccentricity * $eccentricity * $eccentricity / 3072)
                * sin(6 * $latitudeRad));

        $utmEasting =
            (double)(static::SCALING_FACTOR
                * $n
                * ($A
                    + (1 - $t + $c) * pow($A, 3.0) / 6
                    + (5 - 18 * $t + $t * $t + 72 * $c - 58 * $eccentricityPrimeSquared)
                    * pow($A, 5.0)
                    / 120)
                + 500000.0);

        $utmNorthing =
            (double)(static::SCALING_FACTOR
                * ($M
                    + $n
                    * tan($latitudeRad)
                    * ($A * $A / 2
                        + (5 - $t + (9 * $c) + (4 * $c * $c)) * pow($A, 4.0) / 24
                        + (61 - (58 * $t) + ($t * $t) + (600 * $c) - (330 * $eccentricityPrimeSquared))
                        * pow($A, 6.0)
                        / 720)));

        // Adjust for the southern hemisphere
        if ($latitude < 0) {
            $utmNorthing += 10000000.0;
        }

        return $longitudinalZone . $utmZone . ' ' . round($utmEasting) . ' ' . round($utmNorthing);
    }

    private static function getEccentricityOfReferenceEllipsoid(): float
    {
        return ((self::ELLIPSOID_MAJOR_AXIS * self::ELLIPSOID_MAJOR_AXIS) - (self::ELLIPSOID_MINOR_AXIS * self::ELLIPSOID_MINOR_AXIS)) / (self::ELLIPSOID_MAJOR_AXIS * self::ELLIPSOID_MAJOR_AXIS);
    }

    /**
     * Get the longitudinal zone
     * @see http://www.dmap.co.uk/utmworld.htm
     *
     * @param float $latitude
     * @param float $longitude
     * @return int
     */
    private static function getLongitudinalZone(float $latitude, float $longitude): int
    {
        if (($latitude >= 56.0) && ($latitude < 64.0)
            && ($longitude >= 3.0) && ($longitude < 12.0)) {
            return 32;
        }

        // Special zones for Svalbard
        if (($latitude >= 72.0) && ($latitude < 84.0)) {
            if (($longitude >= 0.0) && ($longitude < 9.0)) {
                return 31;
            }

            if (($longitude >= 9.0) && ($longitude < 21.0)) {
                return 33;
            }

            if (($longitude >= 21.0) && ($longitude < 33.0)) {
                return 35;
            }

            if (($longitude >= 33.0) && ($longitude < 42.0)) {
                return 37;
            }
        }

        return (int)(($longitude + 180.0) / 6.0) + 1;
    }

    private static function getLatitudinalZone(float $latitude): string
    {
        return 'CDEFGHJKLMNPQRSTUVWXX'[(int)(($latitude + 80) / 8)];
    }
}