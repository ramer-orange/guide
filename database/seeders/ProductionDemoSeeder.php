<?php

namespace Database\Seeders;

use App\Models\AdditionalComment;
use App\Models\PackingItem;
use App\Models\Plan;
use App\Models\Souvenir;
use App\Models\TravelOverview;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ProductionDemoSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::firstOrCreate(
            ['email' => 'ra.mer.web1111@gmail.com'],
            [
                'name' => 'Ramer',
                'password' => Hash::make('11111111'),
            ],
        );

        $testUser = User::updateOrCreate(
            ['email' => 'tezst@test.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('11111111'),
            ],
        );

        $this->seedTravel($owner, [
            'title' => '沖縄3日間リゾート旅行',
            'overviewText' => '那覇、北谷、美ら海水族館を巡る3日間の旅行しおり。移動・観光・食事の流れを確認できます。',
            'plans' => [
                ['date' => '2026-07-18', 'time' => '09:30', 'plans_title' => '那覇空港 到着', 'content' => 'レンタカーを受け取り、荷物を積んで北谷方面へ出発。'],
                ['date' => '2026-07-18', 'time' => '12:00', 'plans_title' => 'アメリカンビレッジでランチ', 'content' => '海沿いのカフェで昼食。食後に周辺を散策。'],
                ['date' => '2026-07-19', 'time' => '10:00', 'plans_title' => '美ら海水族館', 'content' => 'ジンベエザメの水槽を見学。チケットは事前購入済み。'],
                ['date' => '2026-07-19', 'time' => '17:30', 'plans_title' => 'サンセットビーチ', 'content' => '夕日を見ながら写真撮影。天候次第で予定変更。'],
                ['date' => '2026-07-20', 'time' => '11:00', 'plans_title' => '国際通りで買い物', 'content' => 'お土産を購入して空港へ移動。'],
            ],
            'packingItems' => ['航空券', '運転免許証', '水着', '日焼け止め', 'モバイルバッテリー'],
            'souvenirs' => ['紅いもタルト', 'ちんすこう', 'シーサー置物'],
            'comments' => [
                ['title' => 'レンタカー', 'text' => '空港到着後、予約名を伝えて受け取り。返却前に満タン給油。'],
                ['title' => '雨天時', 'text' => '屋外予定が難しい場合はDMMかりゆし水族館へ変更。'],
            ],
        ]);

        $this->seedTravel($testUser, [
            'title' => '京都週末さんぽ',
            'overviewText' => '清水寺、祇園、嵐山を巡る週末旅行。徒歩と電車中心のゆったりプラン。',
            'plans' => [
                ['date' => '2026-06-06', 'time' => '10:00', 'plans_title' => '京都駅 集合', 'content' => '中央口で集合。荷物をコインロッカーへ預ける。'],
                ['date' => '2026-06-06', 'time' => '11:30', 'plans_title' => '清水寺', 'content' => '参道を散策しながら清水寺へ。混雑するので早めに移動。'],
                ['date' => '2026-06-06', 'time' => '18:00', 'plans_title' => '祇園で夕食', 'content' => '予約済みの和食店で夕食。'],
                ['date' => '2026-06-07', 'time' => '09:30', 'plans_title' => '嵐山 竹林の小径', 'content' => '朝の時間帯に散策して写真撮影。'],
                ['date' => '2026-06-07', 'time' => '14:00', 'plans_title' => '京都駅でお土産購入', 'content' => '八つ橋と抹茶菓子を購入して解散。'],
            ],
            'packingItems' => ['歩きやすい靴', '折りたたみ傘', '交通系ICカード', 'カメラ', '常備薬'],
            'souvenirs' => ['生八つ橋', '抹茶ラングドシャ', '京漬物'],
            'comments' => [
                ['title' => '移動メモ', 'text' => '市バスは混みやすいので、地下鉄と徒歩を優先する。'],
                ['title' => '服装', 'text' => '朝晩は冷える可能性があるため羽織ものを持参。'],
            ],
        ]);
    }

    private function seedTravel(User $user, array $travelData): void
    {
        DB::transaction(function () use ($user, $travelData) {
            $travel = TravelOverview::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'title' => $travelData['title'],
                ],
                ['overviewText' => $travelData['overviewText']],
            );

            $travel->update(['overviewText' => $travelData['overviewText']]);

            Plan::where('travel_id', $travel->id)->delete();
            PackingItem::where('travel_id', $travel->id)->delete();
            Souvenir::where('travel_id', $travel->id)->delete();
            AdditionalComment::where('travel_id', $travel->id)->delete();

            foreach ($travelData['plans'] as $index => $plan) {
                Plan::create([
                    'travel_id' => $travel->id,
                    'date' => $plan['date'],
                    'time' => $plan['time'],
                    'plans_title' => $plan['plans_title'],
                    'content' => $plan['content'],
                    'order' => $index + 1,
                ]);
            }

            foreach ($travelData['packingItems'] as $index => $item) {
                PackingItem::create([
                    'travel_id' => $travel->id,
                    'user_id' => $user->id,
                    'packing_name' => $item,
                    'packing_is_checked' => false,
                    'order' => $index + 1,
                ]);
            }

            foreach ($travelData['souvenirs'] as $index => $item) {
                Souvenir::create([
                    'travel_id' => $travel->id,
                    'souvenir_name' => $item,
                    'souvenir_is_checked' => false,
                    'order' => $index + 1,
                ]);
            }

            foreach ($travelData['comments'] as $index => $comment) {
                AdditionalComment::create([
                    'travel_id' => $travel->id,
                    'additionalComment_title' => $comment['title'],
                    'additionalComment_text' => $comment['text'],
                    'order' => $index + 1,
                ]);
            }

            DB::table('travel_members')->updateOrInsert(
                [
                    'travel_id' => $travel->id,
                    'user_id' => $user->id,
                ],
                [
                    'role' => 'owner',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        });
    }
}
