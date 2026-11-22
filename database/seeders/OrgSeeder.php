<?php

namespace Database\Seeders;

use App\Models\Chapter;
use App\Models\State;
use Illuminate\Database\Seeder;

/**
 * Nigeria's 36 states + FCT (ISO 3166-2:NG codes).
 *
 * Chapters are PLACEHOLDERS - one per state, named after the state capital -
 * until the secretariat imports the real chapter list (open question in the PRD).
 */
class OrgSeeder extends Seeder
{
    /** @var array<string, array{0: string, 1: string}> code => [state, capital] */
    public const STATES = [
        'AB' => ['Abia', 'Umuahia'],
        'AD' => ['Adamawa', 'Yola'],
        'AK' => ['Akwa Ibom', 'Uyo'],
        'AN' => ['Anambra', 'Awka'],
        'BA' => ['Bauchi', 'Bauchi'],
        'BY' => ['Bayelsa', 'Yenagoa'],
        'BE' => ['Benue', 'Makurdi'],
        'BO' => ['Borno', 'Maiduguri'],
        'CR' => ['Cross River', 'Calabar'],
        'DE' => ['Delta', 'Asaba'],
        'EB' => ['Ebonyi', 'Abakaliki'],
        'ED' => ['Edo', 'Benin City'],
        'EK' => ['Ekiti', 'Ado-Ekiti'],
        'EN' => ['Enugu', 'Enugu'],
        'FC' => ['Federal Capital Territory', 'Abuja'],
        'GO' => ['Gombe', 'Gombe'],
        'IM' => ['Imo', 'Owerri'],
        'JI' => ['Jigawa', 'Dutse'],
        'KD' => ['Kaduna', 'Kaduna'],
        'KN' => ['Kano', 'Kano'],
        'KT' => ['Katsina', 'Katsina'],
        'KE' => ['Kebbi', 'Birnin Kebbi'],
        'KO' => ['Kogi', 'Lokoja'],
        'KW' => ['Kwara', 'Ilorin'],
        'LA' => ['Lagos', 'Ikeja'],
        'NA' => ['Nasarawa', 'Lafia'],
        'NI' => ['Niger', 'Minna'],
        'OG' => ['Ogun', 'Abeokuta'],
        'ON' => ['Ondo', 'Akure'],
        'OS' => ['Osun', 'Osogbo'],
        'OY' => ['Oyo', 'Ibadan'],
        'PL' => ['Plateau', 'Jos'],
        'RI' => ['Rivers', 'Port Harcourt'],
        'SO' => ['Sokoto', 'Sokoto'],
        'TA' => ['Taraba', 'Jalingo'],
        'YO' => ['Yobe', 'Damaturu'],
        'ZA' => ['Zamfara', 'Gusau'],
    ];

    public function run(): void
    {
        foreach (self::STATES as $code => [$name, $capital]) {
            $state = State::query()->updateOrCreate(['code' => $code], ['name' => $name]);

            Chapter::query()->firstOrCreate(
                ['state_id' => $state->id, 'name' => $capital.' Chapter'],
                ['lga' => $capital],
            );
        }
    }
}
