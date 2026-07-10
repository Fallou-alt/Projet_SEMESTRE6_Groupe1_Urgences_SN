<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('victimes', function (Blueprint $table) {
            $table->id();
            // lien vers l'incident concerné, suppression en cascade
            $table->foreignId('incident_id')->constrained('incidents')->cascadeOnDelete();
            $table->string('nom');
            $table->string('prenom');
            // âge optionnel, pas toujours connu sur le terrain
            $table->integer('age')->nullable();
            $table->enum('sexe', ['homme', 'femme', 'inconnu'])->default('inconnu');
            $table->string('telephone')->nullable();
            // groupe sanguin utile pour les équipes médicales
            $table->enum('groupe_sanguin', ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'inconnu'])->default('inconnu');
            // état de santé : de léger à décédé
            $table->enum('etat', ['leger', 'grave', 'critique', 'decede', 'inconnu'])->default('inconnu');
            $table->text('observations')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('victimes');
    }
};
