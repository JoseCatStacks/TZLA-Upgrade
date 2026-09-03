<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PortalController extends Controller
{
    public function show(string $tab): View|RedirectResponse
    {
        if ($tab === 'staking') {
            return redirect()->route('staking');
        }

        $page = config("portal.tabs.{$tab}");

        abort_if(! is_array($page), 404);

        return view('portal', [
            'title' => $page['title'] ?? ucfirst($tab),
            'url' => $page['url'] ?: null,
            'embed' => (bool) ($page['embed'] ?? false),
            'desc' => $page['desc'] ?? null,
            'active' => $tab,
        ]);
    }
}
