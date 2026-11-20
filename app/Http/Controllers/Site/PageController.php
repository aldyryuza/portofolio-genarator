<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Certification;
use App\Models\Education;
use App\Models\Experience;
use App\Models\SkillGroup;
use Illuminate\View\View;

class PageController extends Controller
{
    public function about(): View
    {
        return view('site.about', [
            'skillGroups' => SkillGroup::withPublishedSkills(),
        ]);
    }

    public function experience(): View
    {
        return view('site.experience', [
            'experiences' => Experience::query()->published()->chronological()->get(),
        ]);
    }

    public function education(): View
    {
        return view('site.education', [
            'educations' => Education::query()->published()->chronological()->get(),
            'certifications' => Certification::query()->published()->with('file')->chronological()->get(),
        ]);
    }
}
