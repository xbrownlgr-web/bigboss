<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Résultat normal de l'analyse : aucune sélection n'a une valeur positive vérifiable.
 * Rien n'est publié plutôt que de forcer un coupon.
 */
class NoValueBetException extends RuntimeException
{
}
