<?php

namespace Database\Seeders;

use App\Models\DdcClass;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DdcSummarySeeder extends Seeder
{
    public function run(): void
    {
        $entries = [
            ['000', 'Computer science, knowledge & systems'],
            ['010', 'Bibliographies'],
            ['020', 'Library & information sciences'],
            ['030', 'Encyclopedias & books of facts'],
            ['040', 'Unassigned'],
            ['050', 'Magazines, journals & serials'],
            ['060', 'Associations, organizations & museums'],
            ['070', 'News media, journalism & publishing'],
            ['080', 'Quotations'],
            ['090', 'Manuscripts & rare books'],
            ['100', 'Philosophy'],
            ['110', 'Metaphysics'],
            ['120', 'Epistemology'],
            ['130', 'Parapsychology & occultism'],
            ['140', 'Philosophical schools of thought'],
            ['150', 'Psychology'],
            ['160', 'Logic'],
            ['170', 'Ethics'],
            ['180', 'Ancient, medieval & eastern philosophy'],
            ['190', 'Modern western philosophy'],
            ['200', 'Religion'],
            ['210', 'Philosophy & theory of religion'],
            ['220', 'The Bible'],
            ['230', 'Christianity & Christian theology'],
            ['240', 'Christian practice & observance'],
            ['250', 'Christian pastoral practice & religious orders'],
            ['260', 'Christian organization, social work & worship'],
            ['270', 'History of Christianity'],
            ['280', 'Christian denominations'],
            ['290', 'Other religions'],
            ['300', 'Social sciences'],
            ['310', 'Statistics'],
            ['320', 'Political science'],
            ['330', 'Economics'],
            ['340', 'Law'],
            ['350', 'Public administration & military science'],
            ['360', 'Social services; associations'],
            ['370', 'Education'],
            ['380', 'Commerce, communications & transportation'],
            ['390', 'Customs, etiquette & folklore'],
            ['400', 'Language'],
            ['410', 'Linguistics'],
            ['420', 'English & Old English (Anglo-Saxon)'],
            ['430', 'Germanic languages'],
            ['440', 'Romance languages'],
            ['450', 'Italian, Romanian & related languages'],
            ['460', 'Spanish & Portuguese languages'],
            ['470', 'Latin & Italic languages'],
            ['480', 'Classical Greek & Hellenic languages'],
            ['490', 'Other languages'],
            ['500', 'Science'],
            ['510', 'Mathematics'],
            ['520', 'Astronomy'],
            ['530', 'Physics'],
            ['540', 'Chemistry'],
            ['550', 'Earth sciences & geology'],
            ['560', 'Fossils & prehistoric life'],
            ['570', 'Life sciences; biology'],
            ['580', 'Plants (Botany)'],
            ['590', 'Animals (Zoology)'],
            ['600', 'Technology'],
            ['610', 'Medicine & health'],
            ['620', 'Engineering & allied operations'],
            ['630', 'Agriculture'],
            ['640', 'Home & family management'],
            ['650', 'Management & public relations'],
            ['660', 'Chemical engineering'],
            ['670', 'Manufacturing'],
            ['680', 'Manufacture for specific uses'],
            ['690', 'Buildings'],
            ['700', 'Arts & recreation'],
            ['710', 'Landscaping & area planning'],
            ['720', 'Architecture'],
            ['730', 'Sculpture'],
            ['740', 'Drawing & decorative arts'],
            ['750', 'Painting'],
            ['760', 'Graphic arts'],
            ['770', 'Photography'],
            ['780', 'Music'],
            ['790', 'Sports & recreation'],
            ['800', 'Literature'],
            ['810', 'American literature in English'],
            ['820', 'English & Old English literatures'],
            ['830', 'Literatures of Germanic languages'],
            ['840', 'Literatures of Romance languages'],
            ['850', 'Italian, Romanian & related literatures'],
            ['860', 'Spanish & Portuguese literatures'],
            ['870', 'Latin & Italic literatures'],
            ['880', 'Classical Greek & Hellenic literatures'],
            ['890', 'Literatures of other languages'],
            ['900', 'History & geography'],
            ['910', 'Geography & travel'],
            ['920', 'Biography, genealogy & insignia'],
            ['930', 'History of ancient world'],
            ['940', 'History of Europe'],
            ['950', 'History of Asia'],
            ['960', 'History of Africa'],
            ['970', 'History of North America'],
            ['980', 'History of South America'],
            ['990', 'History of other areas'],
        ];

        foreach ($entries as [$code, $name]) {
            $parentId = null;
            $level = 1;
            if ($code !== '000' && (int) $code % 100 !== 0) {
                $parentCode = substr($code, 0, 1) . '00';
                $parentId = DdcClass::query()->where('code', $parentCode)->value('id');
                $level = 2;
            }

            DdcClass::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'normalized_name' => $this->normalize($name),
                    'parent_id' => $parentId,
                    'level' => $level,
                ]
            );
        }
    }

    private function normalize(string $text): string
    {
        return (string) Str::of($text)
            ->lower()
            ->replaceMatches('/[^\p{L}\p{N}\s]/u', ' ')
            ->squish();
    }
}
