<?php

use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Artisan;


// Agendamento da sincronização de metas:
// Roda de hora em hora, apenas durante o horário comercial (das 08:00 às 18:00)
Schedule::command('metas:sincronizar')
    ->hourly()
    ->between('08:00', '18:00');