<?php

namespace App\Controller;

use App\Repository\EquipementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

class InventaireController extends AbstractController
{
    #[Route('/inventaire', name: 'app_inventaire_index')]
    public function index(EquipementRepository $equipementRepository)
    {
        // Logique pour afficher l'inventaire
        return $this->render('inventaire/index.html.twig',[
            'equipements' => $equipementRepository->findAll(),
        ]);
    }

    public function details($id)
    {
        // Logique pour afficher les détails d'un équipement spécifique
        return $this->render('inventaire/details.html.twig', ['id' => $id]);
    }

}
