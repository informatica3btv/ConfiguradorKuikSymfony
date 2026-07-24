<?php

namespace App\Service;

/**
 * Resuelve a qué "tipo" (typeKey de ProductTypeCategory) pertenece cada
 * clave `size` usada en la tabla de productos del presupuesto/PDF.
 */
class ProductTypeResolver
{
    public function resolve(string $size): string
    {
        if (str_starts_with($size, 'columna_') || $size === 'columna') {
            return 'columna';
        }
        if ($size === 'control') {
            return 'control';
        }
        if ($size === 'bandeja') {
            return 'bandeja';
        }
        if (str_starts_with($size, 'lateral_') || $size === 'lateral') {
            return 'side';
        }
        if (str_starts_with($size, 'mailbox_col_') || $size === 'buzon_group') {
            return 'mailbox';
        }
        if (str_starts_with($size, 'brazo_')) {
            return 'brazo';
        }
        if (str_starts_with($size, 'pata_')) {
            return 'pata';
        }
        if ($size === 'instalacion_ref') {
            return 'instalacion';
        }
        if ($size === 'color_door' || $size === 'color_body') {
            return 'color';
        }
        if ($size === 'placa') {
            return 'placa';
        }
        if ($size === 'colgador') {
            return 'colgador';
        }
        if ($size === 'envolvente_buzon') {
            return 'envolvente';
        }
        if (str_starts_with($size, 'tejado_')) {
            return 'roof';
        }
        if (str_ends_with($size, '_meth')) {
            return 'door';
        }

        return 'door';
    }
}
