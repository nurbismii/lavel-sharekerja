<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('profile');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:150', 'phone' => 'nullable|string|max:30', 'location' => 'nullable|string|max:150']);
        $request->user()->forceFill($data)->save();

        return back()->with('success', 'Profil diperbarui. Lamaran lama tetap menggunakan data saat dikirim.');
    }
}
