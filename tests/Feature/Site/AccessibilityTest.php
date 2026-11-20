<?php

use App\Models\Certification;
use App\Models\Education;
use App\Models\Experience;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Resume;
use App\Models\Skill;
use App\Models\User;

/**
 * Structural checks that Lighthouse/axe would also flag. They do not replace a
 * real browser audit (contrast in context, focus order, screen-reader testing).
 */
beforeEach(function () {
    $owner = User::factory()->owner()->create();
    Profile::factory()->create(['user_id' => $owner->id, 'public_email' => 'hello@example.com', 'biography' => "Para one.\n\nPara two."]);
    $project = Project::factory()->published()->featured()->create(['slug' => 'a11y-project', 'summary' => 'Summary', 'outcome' => 'Outcome']);
    $project->skills()->attach(Skill::factory()->create());
    Experience::factory()->published()->create(['achievements_json' => ['Did a thing']]);
    Education::factory()->published()->create();
    Certification::factory()->published()->create(['credential_url' => 'https://verify.example.com/x']);
    Resume::factory()->published()->create(['is_default' => true]);
});

dataset('content pages', [
    'home' => ['/'],
    'about' => ['/about'],
    'projects' => ['/projects'],
    'project detail' => ['/projects/a11y-project'],
    'experience' => ['/experience'],
    'education' => ['/education'],
    'cv' => ['/cv'],
    'contact' => ['/contact'],
    'login' => ['/login'],
]);

function domFor(string $html): DOMXPath
{
    $dom = new DOMDocument;
    @$dom->loadHTML($html, LIBXML_NOERROR);

    return new DOMXPath($dom);
}

test('pages have one h1 and headings never skip a level', function (string $path) {
    $xpath = domFor($this->get($path)->assertOk()->getContent());
    $levels = [];

    foreach ($xpath->query('//h1|//h2|//h3|//h4|//h5|//h6') as $heading) {
        $levels[] = (int) substr($heading->nodeName, 1);
    }

    expect(array_count_values($levels)[1] ?? 0)->toBe(1, "{$path} must have exactly one h1");

    foreach ($levels as $index => $level) {
        if ($index > 0) {
            expect($level - $levels[$index - 1])->toBeLessThanOrEqual(1, "{$path} skips from h{$levels[$index - 1]} to h{$level}");
        }
    }
})->with('content pages');

test('images have alt attributes and form fields have labels', function (string $path) {
    $xpath = domFor($this->get($path)->getContent());

    foreach ($xpath->query('//img') as $img) {
        expect($img->hasAttribute('alt'))->toBeTrue("An image on {$path} has no alt attribute");
    }

    foreach ($xpath->query('//input[not(@type="hidden")]|//select|//textarea') as $field) {
        $id = $field->getAttribute('id');
        $hasLabel = $id !== '' && $xpath->query("//label[@for='{$id}']")->length > 0;
        $wrapped = $xpath->query('ancestor::label', $field)->length > 0;
        $hidden = $xpath->query('ancestor::*[@aria-hidden="true"]', $field)->length > 0;

        expect($hasLabel || $wrapped || $hidden)->toBeTrue("Field {$id} on {$path} has no label");
    }
})->with('content pages');

test('pages declare a language, a skip link and a main landmark', function (string $path) {
    $xpath = domFor($this->get($path)->getContent());

    expect($xpath->query('//html[@lang]')->length)->toBe(1)
        ->and($xpath->query('//main')->length)->toBe(1);

    if ($path !== '/login') {
        expect($xpath->query('//a[@href="#main"]')->length)->toBe(1);
    }
})->with('content pages');
