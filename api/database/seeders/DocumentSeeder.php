<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\Tenant;
use App\Services\Indexing\TextChunker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DocumentSeeder extends Seeder
{
    public function __construct(private TextChunker $textChunker)
    {
    }

    public function run(): void
    {
        foreach (Tenant::all() as $tenant) {
            tenancy()->initialize($tenant);

            for ($i = 1; $i <= 5; $i++) {
                $content = $this->generateLongDocumentContent();

                $document = Document::create([
                    'source_type' => 'google_drive',
                    'source_id' => Str::uuid(),
                    'source_url' => fake()->url(),
                    'title' => fake()->catchPhrase() . ' - Document ' . $i,
                    'preview' => Str::substr($content, 0, 100),
                    'priority' => 'high',
                    'metadata' => [
                        'author' => fake()->name(),
                        'created_date' => fake()->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
                        'document_type' => fake()->randomElement(['proposal', 'report', 'manual', 'specification', 'presentation']),
                        'department' => fake()->randomElement(['Engineering', 'Marketing', 'Sales', 'HR', 'Operations', 'Finance']),
                        'word_count' => str_word_count($content),
                    ],
                    'indexed_at' => fake()->dateTimeBetween('-1 month', 'now'),
                ]);

                // Use TextChunker to create chunks
                $chunks = $this->textChunker->chunk($content);

                foreach ($chunks as $index => $chunkContent) {
                    DocumentChunk::create([
                        'document_id' => $document->id,
                        'body' => $chunkContent,
                        'position' => $index,
                    ]);
                }
            }
        }
    }

    private function generateLongDocumentContent(): string
    {
        $sections = [];
        $numSections = fake()->numberBetween(8, 15); // 8-15 sections for 1-10 pages

        // Document title and introduction
        $sections[] = "# " . fake()->catchPhrase();
        $sections[] = $this->generateParagraphs(2, 4);

        for ($i = 0; $i < $numSections; $i++) {
            // Section header
            $sections[] = "## " . fake()->sentence(3, true);

            // Section content
            $paragraphCount = fake()->numberBetween(3, 8);
            $sections[] = $this->generateParagraphs($paragraphCount, $paragraphCount);

            // Sometimes add bullet points
            if (fake()->boolean(30)) {
                $sections[] = $this->generateBulletPoints();
            }

            // Sometimes add numbered lists
            if (fake()->boolean(20)) {
                $sections[] = $this->generateNumberedList();
            }
        }

        // Add conclusion
        $sections[] = "## Conclusion";
        $sections[] = $this->generateParagraphs(2, 3);

        return implode("\n\n", $sections);
    }

    private function generateParagraphs(int $min, int $max): string
    {
        $paragraphs = [];
        $count = fake()->numberBetween($min, $max);

        for ($i = 0; $i < $count; $i++) {
            $sentences = [];
            $sentenceCount = fake()->numberBetween(4, 8);

            for ($j = 0; $j < $sentenceCount; $j++) {
                $sentences[] = fake()->sentence(fake()->numberBetween(8, 20));
            }

            $paragraphs[] = implode(' ', $sentences);
        }

        return implode("\n\n", $paragraphs);
    }

    private function generateBulletPoints(): string
    {
        $points = [];
        $count = fake()->numberBetween(3, 7);

        for ($i = 0; $i < $count; $i++) {
            $points[] = "- " . fake()->sentence(fake()->numberBetween(5, 15));
        }

        return implode("\n", $points);
    }

    private function generateNumberedList(): string
    {
        $items = [];
        $count = fake()->numberBetween(3, 6);

        for ($i = 1; $i <= $count; $i++) {
            $items[] = "$i. " . fake()->sentence(fake()->numberBetween(6, 12));
        }

        return implode("\n", $items);
    }
}
