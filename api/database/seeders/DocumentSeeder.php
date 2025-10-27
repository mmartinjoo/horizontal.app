<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\DocumentComment;
use App\Models\Participant;
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

            $participants = Participant::factory(8)->create();
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

                // Create comments for this document
                $commentCount = fake()->numberBetween(2, 6);
                for ($j = 0; $j < $commentCount; $j++) {
                    $author = $participants->random();
                    $commentedAt = now();

                    DocumentComment::create([
                        'document_id' => $document->id,
                        'body' => $this->generateComment(),
                        'author_id' => $author->id,
                        'commented_at' => $commentedAt,
                        'comment_id' => Str::uuid(),
                        'metadata' => [
                            'thread_id' => fake()->optional(0.3)->uuid(),
                            'reply_to' => fake()->optional(0.2)->uuid(),
                            'edited' => fake()->boolean(15),
                            'reactions' => fake()->optional(0.4)->randomElements(['👍', '👎', '❤️', '😊', '🎉', '👀'], fake()->numberBetween(0, 3)),
                        ],
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

    private function generateComment(): string
    {
        $commentTypes = [
            'question' => [
                'Could you clarify the section about {topic}?',
                'What are the implications of {topic}?',
                'How does this relate to {topic}?',
                'Can we get more details on {topic}?',
                'Is there supporting data for {topic}?',
            ],
            'feedback' => [
                'This section is very well written and clear.',
                'Great analysis here. Really helpful insights.',
                'I think this could be expanded with more examples.',
                'This aligns well with our current strategy.',
                'Excellent work on the research methodology.',
                'The conclusions are well-supported by the data.',
            ],
            'suggestion' => [
                'Consider adding a timeline for implementation.',
                'It might be worth including cost estimates here.',
                'We should probably mention the regulatory requirements.',
                'This would benefit from a risk assessment section.',
                'Perhaps we could include some case studies.',
                'A visual diagram would help illustrate this concept.',
            ],
            'approval' => [
                'Approved for next phase.',
                'Looks good to proceed.',
                'This meets our requirements.',
                'Ready for implementation.',
                'No concerns from my end.',
            ],
            'concern' => [
                'I have some concerns about the timeline proposed here.',
                'The budget estimates seem optimistic.',
                'We need to consider the technical constraints.',
                'This might conflict with our other initiatives.',
                'Have we considered the potential risks?',
            ],
        ];

        $type = fake()->randomElement(array_keys($commentTypes));
        $template = fake()->randomElement($commentTypes[$type]);

        // Replace {topic} placeholder with relevant terms
        $topics = [
            'implementation strategy',
            'budget allocation',
            'resource requirements',
            'timeline',
            'methodology',
            'risk mitigation',
            'stakeholder engagement',
            'success metrics',
            'market analysis',
            'technical approach',
        ];

        return str_replace('{topic}', fake()->randomElement($topics), $template);
    }
}
