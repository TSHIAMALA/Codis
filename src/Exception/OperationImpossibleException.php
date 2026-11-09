<?php

namespace App\Exception;

/**
 * Opération métier impossible (stock insuffisant, règle non respectée...).
 * Le message est destiné à l'utilisateur ; l'opération entière est annulée (rollback).
 */
final class OperationImpossibleException extends \DomainException
{
}
