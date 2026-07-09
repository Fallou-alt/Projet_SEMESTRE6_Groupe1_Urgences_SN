<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('structures', function (Blueprint $table) {
            $table->id();
            // Informations d'identification de la structure
            $table->string('nom');
            $table->string('sigle')->nullable();
            // Type de service de secours ou de sécurité
            $table->enum('type', ['pompiers', 
            'samu', 
            'police',
             'gendarmerie',
            'marine', 
             'protection_civile',
              'autre']);
              // Localisation administrative
            $table->string('region')->nullable();
            $table->string('departement')->nullable();
            $table->string('commune')->nullable();
            $table->string('adresse')->nullable();
            $table->string('telephone')->nullable();
            $table->string('email')->nullable();
             // Responsable associé à la structure
            $table->unsignedBigInteger('responsable_id')->nullable();
            // Indique si la structure est active ou non
             $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        // Ajouter les clés étrangères croisées maintenant que les deux tables existent
        // Mise en place des relations entre utilisateurs et structures
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('structure_id')->references('id')->on('structures')->nullOnDelete();
        });
        // Association du responsable à sa structure

        Schema::table('structures', function (Blueprint $table) {
            $table->foreign('responsable_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['structure_id']);
        });
        Schema::dropIfExists('structures');
    }
};
