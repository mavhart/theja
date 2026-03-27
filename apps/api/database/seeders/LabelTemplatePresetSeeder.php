<?php

namespace Database\Seeders;

use App\Models\LabelTemplate;
use Illuminate\Database\Seeder;

class LabelTemplatePresetSeeder extends Seeder
{
    public function run(): void
    {
        $fields = [
            ['key' => 'barcode', 'label' => 'Barcode', 'visible' => true, 'font_size' => 9],
            ['key' => 'barcode_number', 'label' => 'Codice', 'visible' => true, 'font_size' => 8],
            ['key' => 'brand', 'label' => 'Marchio', 'visible' => true, 'font_size' => 8],
            ['key' => 'model', 'label' => 'Modello', 'visible' => true, 'font_size' => 8],
            ['key' => 'caliber_bridge', 'label' => 'Calibro/Ponte', 'visible' => true, 'font_size' => 8],
            ['key' => 'color', 'label' => 'Colore', 'visible' => true, 'font_size' => 8],
            ['key' => 'sale_price', 'label' => 'Prezzo', 'visible' => true, 'font_size' => 10],
            ['key' => 'supplier', 'label' => 'Fornitore', 'visible' => false, 'font_size' => 7],
        ];

        $presets = [
            ['name' => 'Piccola 37x14', 'paper_format' => 'A4', 'label_width_mm' => 37, 'label_height_mm' => 14, 'cols' => 10, 'rows' => 10, 'margin_top_mm' => 13, 'margin_left_mm' => 8, 'spacing_h_mm' => 2.5, 'spacing_v_mm' => 0],
            ['name' => 'Media 48x25', 'paper_format' => 'A4', 'label_width_mm' => 48.5, 'label_height_mm' => 25.4, 'cols' => 4, 'rows' => 10, 'margin_top_mm' => 10, 'margin_left_mm' => 5, 'spacing_h_mm' => 2.5, 'spacing_v_mm' => 0],
            ['name' => 'Grande 70x36', 'paper_format' => 'A4', 'label_width_mm' => 70, 'label_height_mm' => 36, 'cols' => 2, 'rows' => 12, 'margin_top_mm' => 10, 'margin_left_mm' => 5, 'spacing_h_mm' => 2.5, 'spacing_v_mm' => 0],
            ['name' => 'Media-alta 52x21', 'paper_format' => 'A4', 'label_width_mm' => 52.5, 'label_height_mm' => 21.17, 'cols' => 4, 'rows' => 14, 'margin_top_mm' => 10, 'margin_left_mm' => 5, 'spacing_h_mm' => 2.5, 'spacing_v_mm' => 0],
            ['name' => 'Lenti 63x38', 'paper_format' => 'A4', 'label_width_mm' => 63.5, 'label_height_mm' => 38.1, 'cols' => 3, 'rows' => 7, 'margin_top_mm' => 10, 'margin_left_mm' => 5, 'spacing_h_mm' => 2.5, 'spacing_v_mm' => 0],
        ];

        foreach ($presets as $i => $preset) {
            LabelTemplate::updateOrCreate(
                ['organization_id' => null, 'pos_id' => null, 'name' => $preset['name']],
                [...$preset, 'fields' => $fields, 'is_default' => $i === 0]
            );
        }
    }
}
