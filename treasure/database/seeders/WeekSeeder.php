<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Week;
use App\Models\Word;
use App\Services\Guess\GuessNormalizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class WeekSeeder extends Seeder
{
    public function run(): void
    {
        $normalizer = new GuessNormalizer();

        $weeks = [
            [
                'number' => 1,
                'title' => "Cap'n's Welcome",
                'starts_at' => Carbon::now()->subDays(28),
                'reward_description' => '1 SOL and a swig of grog',
                'words' => [
                    ['answer' => 'parrot', 'hint' => 'A feathered mimic that rides upon a pirate’s shoulder.'],
                    ['answer' => 'compass', 'hint' => 'A brass friend that ever points to the true north.'],
                    ['answer' => 'kraken', 'hint' => 'The great tentacled beast that drags ships beneath the deep.'],
                ],
            ],
            [
                'number' => 2,
                'title' => 'Salt & Sail',
                'starts_at' => Carbon::now()->subDays(21),
                'reward_description' => 'A rare TZLA charm NFT',
                'words' => [
                    ['answer' => 'anchor', 'hint' => 'Iron kiss that holds a ship still against the tide.'],
                    ['answer' => 'lagoon', 'hint' => 'A shallow blue eye of the sea, ringed by land.'],
                    ['answer' => 'mutiny', 'hint' => 'When the crew turns upon the captain.'],
                ],
            ],
            [
                'number' => 3,
                'title' => 'Ghosts of the Deep',
                'starts_at' => Carbon::now()->subDays(14),
                'reward_description' => '5 SOL bounty for the swift',
                'words' => [
                    ['answer' => 'scurvy', 'hint' => 'The sailor’s curse — cured by a squeeze of lime.'],
                    ['answer' => 'cutlass', 'hint' => 'A short curved blade favored on cramped decks.'],
                    ['answer' => 'treasure', 'hint' => 'What every X on every map promises.'],
                ],
            ],
            [
                'number' => 4,
                'title' => 'The Black Spot',
                'starts_at' => Carbon::now()->subDays(7),
                'reward_description' => 'A whispered legend and 2 SOL',
                'words' => [
                    ['answer' => 'plunder', 'hint' => 'The verb and the noun of a pirate’s trade.'],
                    ['answer' => 'galleon', 'hint' => 'A stout ship of many sails, heavy with gold.'],
                    ['answer' => 'doubloon', 'hint' => 'A Spanish coin that clinks in every chest.'],
                ],
            ],
            [
                'number' => 5,
                'title' => "Cap'n's Last Riddle",
                'starts_at' => Carbon::now()->subDay(),
                'reward_description' => 'The grand prize — 10 SOL and a rare TZLA NFT',
                'words' => [
                    ['answer' => 'skull', 'hint' => 'Grinning ivory upon the flag that warns all comers.'],
                    ['answer' => 'lantern', 'hint' => 'A caged flame that guides the night watch.'],
                    ['answer' => 'horizon', 'hint' => 'Where the sky drinks the sea, ever out of reach.'],
                ],
            ],
        ];

        foreach ($weeks as $data) {
            $week = Week::updateOrCreate(
                ['number' => $data['number']],
                [
                    'title' => $data['title'],
                    'starts_at' => $data['starts_at'],
                    'reward_description' => $data['reward_description'],
                ],
            );

            foreach ($data['words'] as $i => $word) {
                Word::updateOrCreate(
                    ['week_id' => $week->id, 'position' => $i + 1],
                    [
                        'answer_normalized' => $normalizer->normalize($word['answer']),
                        'hint' => $word['hint'],
                    ],
                );
            }
        }
    }
}
