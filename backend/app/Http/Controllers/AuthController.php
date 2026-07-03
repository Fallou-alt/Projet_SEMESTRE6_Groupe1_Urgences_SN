<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    // connexion des utilisateurs (admin, responsable, agent)
    public function connexion(Request $request)
    {
        $request->validate([
            'identifiant'  => 'required|string',
            'mot_de_passe' => 'required|string',
        ]);

        $utilisateur = User::where('identifiant', $request->identifiant)->first();

        // vérifier que le compte existe et que le mdp est bon
        if (!$utilisateur || !Hash::check($request->mot_de_passe, $utilisateur->mot_de_passe)) {
            return response()->json(['message' => 'Identifiant ou mot de passe incorrect. Vérifiez vos informations.'], 401);
        }

        if (!$utilisateur->actif) {
            return response()->json(['message' => 'Votre compte a été désactivé.'], 403);
        }

        // je génère un token aléatoire de 60 caractères, suffisant pour ce projet
        $token = Str::random(60);
        $utilisateur->update(['token' => $token]);

        return response()->json([
            'token'       => $token,
            'utilisateur' => [
                'id'           => $utilisateur->id,
                'nom'          => $utilisateur->nom,
                'prenom'       => $utilisateur->prenom,
                'role'         => $utilisateur->role,
                'structure_id' => $utilisateur->structure_id,
            ],
        ]);
    }

    public function deconnexion(Request $request)
    {
        $utilisateur = $request->get('_user');
        if ($utilisateur) {
            $utilisateur->update(['token' => null]);
        }
        return response()->json(['succes' => true]);
    }

    public function modifierMotDePasse(Request $request)
    {
        $request->validate([
            'ancien'  => 'required',
            'nouveau' => 'required|min:6',
        ]);

        $utilisateur = $request->get('_user');

        if (!Hash::check($request->ancien, $utilisateur->mot_de_passe)) {
            return response()->json(['message' => 'Ancien mot de passe incorrect.'], 422);
        }

        $utilisateur->update(['mot_de_passe' => Hash::make($request->nouveau)]);
        return response()->json(['succes' => true]);
    }

    // permet à n'importe quel utilisateur connecté de changer son nom/prénom
    public function modifierProfil(Request $request)
    {
        $request->validate([
            'prenom' => 'required|string|max:100',
            'nom'    => 'required|string|max:100',
        ]);

        $utilisateur = $request->get('_user');
        $utilisateur->update([
            'prenom' => $request->prenom,
            'nom'    => $request->nom,
        ]);

        return response()->json([
            'succes'      => true,
            'utilisateur' => [
                'id'     => $utilisateur->id,
                'nom'    => $utilisateur->nom,
                'prenom' => $utilisateur->prenom,
                'role'   => $utilisateur->role,
            ],
        ]);
    }
}
