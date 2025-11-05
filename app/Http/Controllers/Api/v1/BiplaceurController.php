<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\v1\InstructorController as GenericInstructorController;
use App\Services\InstructorService;
use Illuminate\Http\Request;

/**
 * @deprecated Ce contrôleur est déprécié et sera supprimé dans une future version.
 * Utilisez InstructorController à la place.
 * 
 * Ce contrôleur sert maintenant d'alias vers InstructorController pour rétrocompatibilité.
 * Toutes les routes redirigent vers les équivalents génériques avec activity_type=paragliding.
 */
class BiplaceurController extends Controller
{
    protected InstructorService $instructorService;
    protected GenericInstructorController $instructorController;

    public function __construct(InstructorService $instructorService, GenericInstructorController $instructorController)
    {
        $this->instructorService = $instructorService;
        $this->instructorController = $instructorController;
    }

    /**
     * Liste des biplaceurs (public) - @deprecated
     * Redirige vers InstructorController avec activity_type=paragliding
     */
    public function index(Request $request)
    {
        // Rediriger vers les instructeurs avec filtre paragliding
        $request->merge(['activity_type' => 'paragliding']);
        return $this->instructorController->index($request);
    }

    /**
     * Détails d'un biplaceur (admin) - @deprecated
     * Redirige vers InstructorController
     */
    public function show(int $id)
    {
        return $this->instructorController->show($id);
    }

    /**
     * Créer un biplaceur (admin) - @deprecated
     * Redirige vers InstructorController avec activity_types=['paragliding']
     */
    public function store(Request $request)
    {
        // Ajouter activity_types par défaut pour paragliding
        $request->merge(['activity_types' => ['paragliding']]);
        return $this->instructorController->store($request);
    }

    /**
     * Modifier un biplaceur (admin) - @deprecated
     * Redirige vers InstructorController
     */
    public function update(Request $request, int $id)
    {
        return $this->instructorController->update($request, $id);
    }

    /**
     * Supprimer un biplaceur (admin) - @deprecated
     * Redirige vers InstructorController
     */
    public function destroy(int $id)
    {
        return $this->instructorController->destroy($id);
    }

    /**
     * Mes vols (biplaceur) - @deprecated
     * Redirige vers InstructorController->mySessions()
     */
    public function myFlights(Request $request)
    {
        return $this->instructorController->mySessions($request);
    }

    /**
     * Vols du jour (biplaceur) - @deprecated
     * Redirige vers InstructorController->sessionsToday()
     */
    public function flightsToday(Request $request)
    {
        return $this->instructorController->sessionsToday($request);
    }

    /**
     * Calendrier (biplaceur ou admin) - @deprecated
     * Redirige vers InstructorController->calendar()
     */
    public function calendar(Request $request, ?int $id = null)
    {
        return $this->instructorController->calendar($request, $id);
    }

    /**
     * Mettre à jour disponibilités (biplaceur) - @deprecated
     * Redirige vers InstructorController->updateAvailability()
     */
    public function updateAvailability(Request $request)
    {
        return $this->instructorController->updateAvailability($request);
    }

    /**
     * Marquer vol comme fait (biplaceur) - @deprecated
     * Redirige vers InstructorController->markSessionDone()
     */
    public function markFlightDone(Request $request, int $id)
    {
        return $this->instructorController->markSessionDone($request, $id);
    }

    /**
     * Reporter un vol (biplaceur) - @deprecated
     * Redirige vers InstructorController->rescheduleSession()
     */
    public function rescheduleFlight(Request $request, int $id)
    {
        return $this->instructorController->rescheduleSession($request, $id);
    }

    /**
     * Infos rapides client (biplaceur) - @deprecated
     * Redirige vers InstructorController->quickInfo()
     */
    public function quickInfo(Request $request, int $id)
    {
        return $this->instructorController->quickInfo($request, $id);
    }
}

