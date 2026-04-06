<?php

namespace App\Service;

use App\Repository\DoorRepository;
use App\Repository\RoofRepository;
use App\Repository\SideRepository;

class ConfigurationService
{
    private DoorRepository $doorRepo;
    private SideRepository $sideRepo;
    private RoofRepository $roofRepo;
    private BtvApiService  $btvApi;
    private string         $placaReference;

    public function __construct(
        DoorRepository $doorRepo,
        SideRepository $sideRepo,
        RoofRepository $roofRepo,
        BtvApiService  $btvApi,
        string         $placaReference
    ) {
        $this->doorRepo       = $doorRepo;
        $this->sideRepo       = $sideRepo;
        $this->roofRepo       = $roofRepo;
        $this->btvApi         = $btvApi;
        $this->placaReference = $placaReference;
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

            $globalPlateNumber += max(1, (int) ceil($groupDoorCounter / 16));
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

        $plateNumber = $globalPlateNumber + intdiv($groupDoorCounter - 1, 16);
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
            $totalPlates += max(1, (int) ceil($doorsInGroup / 16));
        }

        $sizeCounts['placa'] = $totalPlates;
        $products['placa']   = $this->placaReference;
        $productInfo['placa'] = $this->btvApi->getProductInfo($this->placaReference, $totalPlates);

        return [
            'products'    => $products,
            'productInfo' => $productInfo,
            'sizeCounts'  => $sizeCounts,
        ];
    }
}
