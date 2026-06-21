<?php

namespace Database\Seeders;

use App\Models\Team;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    /** @var array<int, array{0:string,1:string,2:string,3:string}> name, captain, subcaptain, official */
    private const TEAMS = [
        ['Xplosivos', 'Ricardo', 'Cielo azul', 'Kristen'],
        ['Kilos Mortales', 'Fernando Velasquez', 'Jonathan Paoli', 'Luis Rodrigo Guzman'],
        ['Shadow break', 'Maui Perez', 'Xavi Monzón', 'José Ramos'],
        ['Gan Gan Galaxy', 'James Bojorquez', 'Heythan', 'Don Mario'],
        ['Dragon Knights', 'Yury', 'Guillermo', 'Derek'],
        ['Bladers Fury', 'Steve Solorzano', 'Luis Rios', 'Roberto Lopez'],
        ['Equipo Zooganico', 'Rafael Quevedo', 'Deniss Camey', 'Haydee Ramirez'],
        ['Triple extinción', 'Dany Florian', 'Melman Camargo', 'Jonathan López Marroquín'],
        ['The phantom thieves', 'Carlos Gonzalez', 'Linda choc', 'Haciel Garcia'],
        ['Triada de Asgard', 'Pipo Díaz', 'Jossie Marroquín', 'Jorge Sagastume'],
        ['Team Persona', 'Robin García', 'Fernando Camargo', 'Pana Poyo'],
        ['Blazing Bahamuts', 'Dylan', 'Pablo Morales', 'Luis Mora'],
        ['Sannins', 'Ricardo Sandoval', 'Andrés Lutin', 'Renato Arellano'],
    ];

    public function run(): void
    {
        foreach (self::TEAMS as [$name, $captain, $subcaptain, $official]) {
            $team = Team::firstOrCreate(['name' => $name]);

            $team->members()->updateOrCreate(['role' => 'captain'], ['name' => $captain]);
            $team->members()->updateOrCreate(['role' => 'subcaptain'], ['name' => $subcaptain]);
            $team->members()->updateOrCreate(['role' => 'official'], ['name' => $official]);
        }
    }
}
