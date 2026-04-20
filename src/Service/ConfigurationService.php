<?php

namespace App\Service;

use App\Repository\BandejaRepository;
use App\Repository\DoorRepository;
use App\Repository\EnvolventeRepository;
use App\Repository\RoofRepository;
use App\Repository\SideRepository;

class ConfigurationService
{
    private DoorRepository       $doorRepo;
    private SideRepository       $sideRepo;
    private RoofRepository       $roofRepo;
    private EnvolventeRepository $envolventeRepo;
    private BandejaRepository    $bandejaRepo;
    private BtvApiService        $btvApi;
    private string               $placaReference;
    private int                  $doorsPerPlate;

    public function __construct(
        DoorRepository $doorRepo,
        SideRepository $sideRepo,
        RoofRepository $roofRepo,
        EnvolventeRepository $envolventeRepo,
        BandejaRepository $bandejaRepo,
        BtvApiService  $btvApi,
        string         $placaReference,
        int            $doorsPerPlate = 16
    ) {
        $this->doorRepo       = $doorRepo;
        $this->sideRepo       = $sideRepo;
        $this->roofRepo       = $roofRepo;
        $this->envolventeRepo = $envolventeRepo;
        $this->bandejaRepo    = $bandejaRepo;
        $this->btvApi         = $btvApi;
        $this->placaReference = $placaReference;
        $this->doorsPerPlate  = $doorsPerPlate;
    }

    /**
     * Returns a map of door_number => size for all real doors (excludes screens and mailboxes).
     */
    public function getDoorSizesByNumber(array $payload): array
    {
        $groups  = $payload['groups'] ?? [];
        $columns = $payload['columns'] ?? [];

        if (empty($groups) && !empty($columns)) {
            $groups = [$columns];
        }

        $sizes   = [];
        $doorNum = 0;

        foreach ($groups as $groupCols) {
            if (!is_array($groupCols)) {
                continue;
            }
            foreach ($groupCols as $col) {
                foreach (['top', 'bottom', 'single'] as $part) {
                    foreach ($col[$part]['blocks'] ?? [] as $blk) {
                        $type = is_array($blk) ? ($blk['type'] ?? 'door') : 'door';
                        if ($type !== 'screen' && $type !== 'mailbox') {
                            $doorNum++;
                            $h = is_array($blk) ? (string)($blk['h'] ?? 0) : (string)$blk;
                            $sizes[(string) $doorNum] = $h;
                        }
                    }
                }
            }
        }

        return $sizes;
    }

    public function countDoorsInPayload(array $payload): int
    {
        return count($this->getDoorSizesByNumber($payload));
    }

    /**
     * Removes addon entries for doors that no longer exist or changed size.
     */
    public function cleanAddons(array $payloadArr, array $oldPayload): array
    {
        if (!isset($payloadArr['addons']) || !is_array($payloadArr['addons'])) {
            return $payloadArr;
        }

        $oldSizes  = $this->getDoorSizesByNumber($oldPayload);
        $newSizes  = $this->getDoorSizesByNumber($payloadArr);
        $cleanAddons = [];

        foreach ($newSizes as $doorNum => $size) {
            if (!isset($payloadArr['addons'][$doorNum])) {
                continue;
            }
            if (isset($oldSizes[$doorNum]) && $oldSizes[$doorNum] !== $size) {
                continue;
            }
            $cleanAddons[$doorNum] = $payloadArr['addons'][$doorNum];
        }

        $payloadArr['addons'] = $cleanAddons;

        return $payloadArr;
    }

    /**
     * Annotates every block in the payload with doorNumber, plateNumber, addon flags.
     */
    public function prepareSummaryPayload(array $payload): array
    {
        $groups  = $payload['groups'] ?? [];
        $columns = $payload['columns'] ?? [];

        if (empty($groups) && !empty($columns)) {
            $groups = [$columns];
        }

        $addons            = $payload['addons'] ?? [];
        $globalDoorNumber  = 0;
        $globalPlateNumber = 1;
        $preparedGroups    = [];

        foreach ($groups as $groupCols) {
            if (!is_array($groupCols)) {
                $groupCols = [];
            }

            $groupDoorCounter = 0;
            $preparedCols     = [];

            foreach ($groupCols as $col) {
                $preparedCol = $col;

                if (!empty($col['single']['blocks']) && is_array($col['single']['blocks'])) {
                    $preparedCol['single']['blocks_prepared'] = [];
                    foreach ($col['single']['blocks'] as $blk) {
                        $preparedCol['single']['blocks_prepared'][] = $this->prepareBlock(
                            $blk, $addons, $globalDoorNumber, $groupDoorCounter, $globalPlateNumber
                        );
                    }
                } else {
                    $preparedCol['top']['blocks_prepared'] = [];
                    foreach (($col['top']['blocks'] ?? []) as $blk) {
                        $preparedCol['top']['blocks_prepared'][] = $this->prepareBlock(
                            $blk, $addons, $globalDoorNumber, $groupDoorCounter, $globalPlateNumber
                        );
                    }

                    $preparedCol['bottom']['blocks_prepared'] = [];
                    foreach (($col['bottom']['blocks'] ?? []) as $blk) {
                        $preparedCol['bottom']['blocks_prepared'][] = $this->prepareBlock(
                            $blk, $addons, $globalDoorNumber, $groupDoorCounter, $globalPlateNumber
                        );
                    }
                }

                $preparedCols[] = $preparedCol;
            }

            $globalPlateNumber += max(1, (int) ceil($groupDoorCounter / $this->doorsPerPlate));
            $preparedGroups[]   = $preparedCols;
        }

        $payload['groups'] = $preparedGroups;

        return $payload;
    }

    private function prepareBlock(
        $blk,
        array $addons,
        int   &$globalDoorNumber,
        int   &$groupDoorCounter,
        int   $globalPlateNumber
    ): array {
        if (is_array($blk)) {
            $height = (int) ($blk['h'] ?? 0);
            $type   = $blk['type'] ?? 'door';
        } else {
            $height = (int) $blk;
            $type   = 'door';
            $blk    = ['h' => $height, 'type' => $type];
        }

        $isScreen  = ($type === 'screen');
        $isMailbox = ($type === 'mailbox');

        if ($isScreen || $isMailbox) {
            $blk['doorNumber']   = null;
            $blk['plateNumber']  = null;
            $blk['socket']       = false;
            $blk['usb']          = false;
            $blk['methacrylate'] = false;
            $blk['isScreen']     = $isScreen;
            $blk['isMailbox']    = $isMailbox;

            return $blk;
        }

        $globalDoorNumber++;
        $groupDoorCounter++;

        $plateNumber = $globalPlateNumber + intdiv($groupDoorCounter - 1, $this->doorsPerPlate);
        $sel         = $addons[(string) $globalDoorNumber] ?? $addons[$globalDoorNumber] ?? null;

        $blk['doorNumber']   = $groupDoorCounter;
        $blk['plateNumber']  = $plateNumber;
        $blk['socket']       = is_array($sel) ? !empty($sel['socket'])       : false;
        $blk['usb']          = is_array($sel) ? !empty($sel['usb'])          : false;
        $blk['methacrylate'] = is_array($sel) ? !empty($sel['methacrylate']) : false;
        $blk['isScreen']     = false;
        $blk['isMailbox']    = false;

        return $blk;
    }

    /**
     * Builds the product table data (products, productInfo, sizeCounts).
     */
    public function buildProductTable(array $payload): array
    {
        $serie     = $payload['fondo'] ?? '';
        $placement = $payload['placement'] ?? '';
        $addons    = $payload['addons'] ?? [];
        $doorIndex = 0;
        $sizeCounts = [];

        $groups  = $payload['groups'] ?? [];
        $allCols = [];
        if (!empty($groups)) {
            foreach ($groups as $g) {
                if (is_array($g)) {
                    foreach ($g as $c) { $allCols[] = $c; }
                }
            }
        } else {
            $allCols = $payload['columns'] ?? [];
        }

        foreach ($allCols as $col) {
            foreach (['top', 'bottom', 'single'] as $part) {
                foreach ($col[$part]['blocks'] ?? [] as $blk) {
                    $btype = $blk['type'] ?? 'door';
                    if ($btype !== 'screen' && $btype !== 'mailbox') {
                        $doorIndex++;
                        $size = (string)($blk['h'] ?? '');
                        $sel  = $addons[(string)$doorIndex] ?? $addons[$doorIndex] ?? null;
                        $meth = (bool)(is_array($sel) ? ($sel['methacrylate'] ?? false) : false);
                        $key  = $size . ($meth ? '_meth' : '');
                        $sizeCounts[$key] = ($sizeCounts[$key] ?? 0) + 1;
                    }
                }
            }
        }
        ksort($sizeCounts);

        $products    = [];
        $productInfo = [];
        foreach ($sizeCounts as $key => $count) {
            $meth    = str_ends_with($key, '_meth');
            $size    = $meth ? substr($key, 0, -5) : $key;
            $product = $this->doorRepo->findOneDoorBySerieAndPlaceAndSizeAndMethacrylate($serie, $placement, $size, $meth);
            $products[$key] = $product;
            if ($product) {
                $productInfo[$key] = $this->btvApi->getProductInfo($product->getReference(), $count);
            }
        }

        $groupCount = !empty($groups) ? count($groups) : 1;
        $side       = $this->sideRepo->findOneSideBySerieAndPlace($serie, $placement);
        $sizeCounts['lateral'] = $groupCount;
        $products['lateral']   = $side;
        if ($side) {
            $productInfo['lateral'] = $this->btvApi->getProductInfo($side->getReference(), $groupCount);
        }

        // Bandejas: una por columna que tiene bloques tanto en top como en bottom
        $bandejaCount = 0;
        foreach ($allCols as $col) {
            $hasTop    = !empty($col['top']['blocks'] ?? []);
            $hasBottom = !empty($col['bottom']['blocks'] ?? []);
            if ($hasTop && $hasBottom) {
                $bandejaCount++;
            }
        }
        if ($bandejaCount > 0) {
            $bandeja = $this->bandejaRepo->findOneBySerie($serie);
            $sizeCounts['bandeja'] = $bandejaCount;
            $products['bandeja']   = $bandeja;
            if ($bandeja) {
                $productInfo['bandeja'] = $this->btvApi->getProductInfo($bandeja->getReference(), $bandejaCount);
            }
        }

        $roofGroups = !empty($groups) ? $groups : (!empty($payload['columns']) ? [$payload['columns']] : []);
        $roofCounts = [];
        foreach ($roofGroups as $groupCols) {
            if (!is_array($groupCols)) continue;
            $numCols = count($groupCols);
            $pairs   = intdiv($numCols, 2);
            $singles = $numCols % 2;
            if ($pairs > 0)   { $roofCounts[2] = ($roofCounts[2] ?? 0) + $pairs; }
            if ($singles > 0) { $roofCounts[1] = ($roofCounts[1] ?? 0) + $singles; }
        }
        foreach ($roofCounts as $numCols => $count) {
            $key  = 'tejado_' . $numCols;
            $roof = $this->roofRepo->findOneRoofBySerieAndPlaceAndColumns($serie, $placement, (string) $numCols);
            $sizeCounts[$key] = $count;
            $products[$key]   = $roof;
            if ($roof) {
                $productInfo[$key] = $this->btvApi->getProductInfo($roof->getReference(), $count);
            }
        }

        $totalPlates = 0;
        foreach ($roofGroups as $groupCols) {
            if (!is_array($groupCols)) continue;
            $doorsInGroup = 0;
            foreach ($groupCols as $col) {
                foreach (['top', 'bottom', 'single'] as $part) {
                    foreach ($col[$part]['blocks'] ?? [] as $blk) {
                        $btype = $blk['type'] ?? 'door';
                        if ($btype !== 'screen' && $btype !== 'mailbox') {
                            $doorsInGroup++;
                        }
                    }
                }
            }
            $totalPlates += max(1, (int) ceil($doorsInGroup / $this->doorsPerPlate));
        }

        $sizeCounts['placa'] = $totalPlates;
        $products['placa']   = $this->placaReference;
        $productInfo['placa'] = $this->btvApi->getProductInfo($this->placaReference, $totalPlates);

        // Grupo de buzones (agrupación combinada)
        $agrupacion = $payload['agrupacion_combinada'] ?? false;
        $mbGroup    = $payload['mailboxGroup'] ?? null;
        if ($agrupacion && $mbGroup && !empty($mbGroup['reference'])) {
            $mbRef   = $mbGroup['reference'];
            $mbCells = $mbGroup['cells'] ?? [];
            // Contar celdas activas (filled = true por defecto)
            $mbCount = 0;
            $totalMbCells = ($mbGroup['rows'] ?? 0) * ($mbGroup['cols'] ?? 0);
            for ($i = 0; $i < $totalMbCells; $i++) {
                if ($mbCells[$i] ?? true) {
                    $mbCount++;
                }
            }
            if ($mbCount > 0) {
                $sizeCounts['buzon_group'] = $mbCount;
                $products['buzon_group']   = $mbRef;
                $apiInfo = $this->btvApi->getProductInfo($mbRef, $mbCount);
                if (is_array($apiInfo)) {
                    $apiInfo['_descripcion'] = $mbGroup['descripcion'] ?? null;
                }
                $productInfo['buzon_group'] = $apiInfo;

                // Envolvente para agrupación de buzones
                $mbRows  = (int) ($mbGroup['rows'] ?? 0);
                $mbCols  = (int) ($mbGroup['cols'] ?? 0);
                $mbAlto  = (float) ($mbGroup['alto']  ?? 0); // mm
                $mbAncho = (float) ($mbGroup['ancho'] ?? 0); // mm

                // Rango: 4-15 buzones → pequeño, >15 → grande
                $rango = ($mbCount <= 15) ? 'pequeño' : 'grande';

                // Perímetro en tramos de 0,5 m con mínimo 1 m
                $perimetroMm = 2 * ($mbCols * $mbAncho + $mbRows * $mbAlto);
                $metros = max(1.0, ceil($perimetroMm / 500) / 2);

                $envolvente = $this->envolventeRepo->findOneBy(['tipo' => 'buzon', 'rango' => $rango]);
                if ($envolvente && $metros > 0) {
                    $sizeCounts['envolvente_buzon'] = $metros;
                    $products['envolvente_buzon']   = $envolvente;
                    $envInfo = $this->btvApi->getProductInfo($envolvente->getReference(), $metros);
                    if (is_array($envInfo)) {
                        $envInfo['_descripcion'] = $envolvente->getDescripcion();
                        $envInfo['_metros']      = $metros;
                        $envInfo['_rango']       = $rango;
                    }
                    $productInfo['envolvente_buzon'] = $envInfo;
                }
            }
        }

        return [
            'products'    => $products,
            'productInfo' => $productInfo,
            'sizeCounts'  => $sizeCounts,
        ];
    }
}
