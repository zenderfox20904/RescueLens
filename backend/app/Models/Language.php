<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Language extends Model
{
    use SoftDeletes;

    public const RTL = ['ar', 'ur', 'fa', 'he', 'ps', 'sd', 'yi', 'ckb'];
    protected $guarded = ['id'];
    protected $casts = ['rtl' => 'boolean', 'enabled' => 'boolean', 'hand_translated' => 'boolean'];

    public function getRouteKeyName(): string { return 'code'; }

    public static function present(self $l): array
    {
        return ['code' => $l->code, 'native_name' => $l->native_name, 'english_name' => $l->english_name, 'rtl' => $l->rtl, 'enabled' => $l->enabled, 'hand_translated' => $l->hand_translated];
    }

    /** Major world languages. en and bn are hand translated; others are machine translated on demand and labeled. */
    public static function seedRows(): array
    {
        $rows = [
            ['en', 'English', 'English'], ['bn', 'বাংলা', 'Bengali'], ['ar', 'العربية', 'Arabic'], ['hi', 'हिन्दी', 'Hindi'], ['ur', 'اردو', 'Urdu'],
            ['es', 'Español', 'Spanish'], ['fr', 'Français', 'French'], ['pt', 'Português', 'Portuguese'], ['ru', 'Русский', 'Russian'], ['zh', '中文', 'Chinese'],
            ['ja', '日本語', 'Japanese'], ['ko', '한국어', 'Korean'], ['de', 'Deutsch', 'German'], ['it', 'Italiano', 'Italian'], ['tr', 'Türkçe', 'Turkish'],
            ['fa', 'فارسی', 'Persian'], ['he', 'עברית', 'Hebrew'], ['id', 'Bahasa Indonesia', 'Indonesian'], ['ms', 'Bahasa Melayu', 'Malay'], ['th', 'ไทย', 'Thai'],
            ['vi', 'Tiếng Việt', 'Vietnamese'], ['fil', 'Filipino', 'Filipino'], ['sw', 'Kiswahili', 'Swahili'], ['ha', 'Hausa', 'Hausa'], ['am', 'አማርኛ', 'Amharic'],
            ['ne', 'नेपाली', 'Nepali'], ['si', 'සිංහල', 'Sinhala'], ['ta', 'தமிழ்', 'Tamil'], ['te', 'తెలుగు', 'Telugu'], ['mr', 'मराठी', 'Marathi'],
            ['pa', 'ਪੰਜਾਬੀ', 'Punjabi'], ['gu', 'ગુજરાતી', 'Gujarati'], ['my', 'မြန်မာ', 'Burmese'], ['uk', 'Українська', 'Ukrainian'], ['pl', 'Polski', 'Polish'],
            ['nl', 'Nederlands', 'Dutch'], ['ro', 'Română', 'Romanian'], ['el', 'Ελληνικά', 'Greek'], ['ps', 'پښتو', 'Pashto'], ['yo', 'Yorùbá', 'Yoruba'],
        ];
        return array_map(fn ($r, $i) => ['code' => $r[0], 'native_name' => $r[1], 'english_name' => $r[2], 'rtl' => in_array($r[0], self::RTL, true), 'enabled' => true,
            'hand_translated' => in_array($r[0], ['en', 'bn'], true), 'sort' => $i], $rows, array_keys($rows));
    }
}
