<?php

namespace App\Service;

use App\Repository\BandejaRepository;
use App\Repository\BrazoRepository;
use App\Repository\ColumnaRepository;
use App\Repository\ControlRepository;
use App\Repository\DoorRepository;
use App\Repository\EnvolventeRepository;
use App\Repository\MailboxRepository;
use App\Repository\PataRepository;
use App\Repository\RoofRepository;
use App\Repository\SideRepository;

class ConfigurationService
{
    private DoorRepository       $doorRepo;
    private SideRepository       $sideRepo;
    private RoofRepository       $roofRepo;
    private EnvolventeRepository $envolventeRepo;
    private BandejaRepository    $bandejaRepo;
    private BrazoRepository      $brazoRepo;
    private PataRepository       $pataRepo;
    private ColumnaRepository    $columnaRepo;
    private ControlRepository    $controlRepo;
    private MailboxRepository    $mailboxRepo;
    private BtvApiService        $btvApi;
    private string               $placaReference;
    private string               $colgadorReference;
    private string               $instalacionReference;
    private int                  $doorsPerPlate;

    public function __construct(
        DoorRepository $doorRepo,
        SideRepository $sideRepo,
        RoofRepository $roofRepo,
        EnvolventeRepository $envolventeRepo,
        BandejaRepository $bandejaRepo,
        BrazoRepository $brazoRepo,
        PataRepository $pataRepo,
        ColumnaRepository $columnaRepo,
        ControlRepository $controlRepo,
        MailboxRepository $mailboxRepo,
        BtvApiService  $btvApi,
        string         $placaReference,
        string         $colgadorReference = '',
        string         $instalacionReference = '',
        int            $doorsPerPlate = 16
    ) {
        $this->doorRepo       = $doorRepo;
        $this->sideRepo       = $sideRepo;
        $this->roofRepo       = $roofRepo;
        $this->envolventeRepo = $envolventeRepo;
        $this->bandejaRepo    = $bandejaRepo;
        $this->brazoRepo      = $brazoRepo;
        $this->pataRepo       = $pataRepo;
        $this->columnaRepo    = $columnaRepo;
        $this->controlRepo    = $controlRepo;
        $this->mailboxRepo    = $mailboxRepo;
        $this->btvApi         = $btvApi;
        $this->placaReference       = $placaReference;
        $this->colgadorReference    = $colgadorReference;
        $this->instalacionReference = $instalacionReference;
        $this->doorsPerPlate        = $doorsPerPlate;
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

        $isScreen         = ($type === 'screen');
        $isMailbox        = ($type === 'mailbox');
        $isElecMailbox    = $isMailbox && !empty($blk['electronico']);

        if ($isScreen || ($isMailbox && !$isElecMailbox)) {
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

    public function getInstalacionReference(): string
    {
        return $this->instalacionReference;
    }

    /**
     * Builds the product table data (products, productInfo, sizeCounts).
     */
    public function buildProductTable(array $payload): array
    {
        $serie     = $payload['fondo'] ?? '';
        $placement = $payload['placement'] ?? '';
        $tipo      = $payload['type'] ?? null; // 'home' | 'profesional'
        // Home: interior/exterior solo distingue el tejado; para el resto de refs no filtrar por placement
        $refPlacement = ($tipo === 'home') ? null : $placement;
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

        $mailboxBlockCounts = []; // key => [elec, tarj, count]

        foreach ($allCols as $col) {
            foreach (['top', 'bottom', 'single'] as $part) {
                foreach ($col[$part]['blocks'] ?? [] as $blk) {
                    $btype = $blk['type'] ?? 'door';
                    if ($btype === 'mailbox') {
                        $elec = !empty($blk['electronico']);
                        $tarj = !empty($blk['tarjetero']);
                        $ref  = $blk['reference'] ?? null;
                        $mbKey = $ref
                            ? 'mailbox_col_ref_' . $ref
                            : 'mailbox_col_' . ($elec ? '1' : '0') . '_' . ($tarj ? '1' : '0');
                        if (!isset($mailboxBlockCounts[$mbKey])) {
                            $mailboxBlockCounts[$mbKey] = ['electronico' => $elec, 'tarjetero' => $tarj, 'reference' => $ref, 'count' => 0];
                        }
                        $mailboxBlockCounts[$mbKey]['count']++;
                    } elseif ($btype !== 'screen') {
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
            $product = $this->doorRepo->findOneDoorBySerieAndPlaceAndSizeAndMethacrylate($serie, $refPlacement, $size, $meth, $tipo);
            $products[$key] = $product;
            if ($product) {
                $productInfo[$key] = $this->btvApi->getProductInfo($product->getReference(), $count);
            }
        }

        $groupCount = !empty($groups) ? count($groups) : 1;

        $isBrazos = $tipo === 'home'
            && ($payload['instalacion'] ?? '') === 'soporte_suelo'
            && ($payload['bracketType'] ?? '') === 'brazos';

        $isPatas = $tipo === 'home'
            && ($payload['instalacion'] ?? '') === 'soporte_suelo'
            && ($payload['bracketType'] ?? '') === 'patas';

        // Para Home con patas: 1 kit de patas por agrupación según número de columnas
        if ($isPatas && !empty($groups)) {
            foreach ($groups as $groupCols) {
                if (!is_array($groupCols)) continue;
                $numCols = count($groupCols);
                $pata = $this->pataRepo->findOneBy(['numColumnas' => $numCols, 'tipo' => $tipo])
                     ?? $this->pataRepo->findOneBy(['numColumnas' => $numCols]);
                $pataKey = 'pata_' . $numCols;
                $sizeCounts[$pataKey] = ($sizeCounts[$pataKey] ?? 0) + 1;
                $products[$pataKey]   = $pata;
                if ($pata) {
                    $qty = $sizeCounts[$pataKey];
                    $productInfo[$pataKey] = $this->btvApi->getProductInfo($pata->getReference(), $qty);
                }
            }
        }

        // Para Home con brazos: sin laterales, 1 brazo por agrupación según altura
        if ($isBrazos && !empty($groups)) {
            foreach ($groups as $groupCols) {
                if (!is_array($groupCols)) continue;
                $maxH = 0;
                foreach ($groupCols as $col) {
                    $colH = 0;
                    foreach (['top', 'bottom', 'single'] as $part) {
                        foreach ($col[$part]['blocks'] ?? [] as $blk) {
                            $colH += (int)($blk['h'] ?? 0);
                        }
                    }
                    $maxH = max($maxH, $colH);
                }
                $hUnits   = (string)intdiv($maxH, 10);
                $brazoKey = 'brazo_' . $maxH;
                $brazo = $this->brazoRepo->findOneBy(['altura' => $hUnits, 'tipo' => $tipo])
                      ?? $this->brazoRepo->findOneBy(['altura' => $hUnits])
                      ?? $this->brazoRepo->findOneBy(['altura' => $hUnits . 'H', 'tipo' => $tipo])
                      ?? $this->brazoRepo->findOneBy(['altura' => $hUnits . 'H']);
                $sizeCounts[$brazoKey] = ($sizeCounts[$brazoKey] ?? 0) + 1;
                $products[$brazoKey]   = $brazo;
                if ($brazo) {
                    $qty = $sizeCounts[$brazoKey];
                    $productInfo[$brazoKey] = $this->btvApi->getProductInfo($brazo->getReference(), $qty);
                }
            }
        } elseif ($tipo === 'home' && !empty($groups)) {
            // Para Home sin brazos: lateral por altura de agrupación
            foreach ($groups as $groupCols) {
                if (!is_array($groupCols)) continue;
                $maxH = 0;
                foreach ($groupCols as $col) {
                    $colH = 0;
                    foreach (['top', 'bottom', 'single'] as $part) {
                        foreach ($col[$part]['blocks'] ?? [] as $blk) {
                            $colH += (int)($blk['h'] ?? 0);
                        }
                    }
                    $maxH = max($maxH, $colH);
                }
                $alturaKey  = (string)$maxH;
                $lateralKey = 'lateral_' . $alturaKey;
                $side = $this->sideRepo->findOneSideBySerieAndPlace($serie, $refPlacement, $tipo, $alturaKey);
                $instalacion = $payload['instalacion'] ?? '';
                $lateralPairs = in_array($instalacion, ['empotrado', 'colgado', 'zocalo'], true)
                    ? (int)ceil(count($groupCols) / 5)
                    : 1;
                $sizeCounts[$lateralKey] = ($sizeCounts[$lateralKey] ?? 0) + $lateralPairs;
                $products[$lateralKey]   = $side;
                if ($side) {
                    $qty = $sizeCounts[$lateralKey];
                    $productInfo[$lateralKey] = $this->btvApi->getProductInfo($side->getReference(), $qty);
                }
            }
        } else {
            $side = $this->sideRepo->findOneSideBySerieAndPlace($serie, $refPlacement, $tipo);
            $sizeCounts['lateral'] = $groupCount;
            $products['lateral']   = $side;
            if ($side) {
                $productInfo['lateral'] = $this->btvApi->getProductInfo($side->getReference(), $groupCount);
            }
        }
        
        // Columnas: una por columna en total.
        // Para Home, agrupar columnas por altura total (suma de h de sus bloques) y buscar referencia por altura.
        $totalCols = count($allCols);
        if ($totalCols > 0) {
            if ($tipo === 'home') {
                // Calcular altura total de cada columna y agrupar
                $colsByAltura = [];
                foreach ($allCols as $col) {
                    $h = 0;
                    foreach (['top', 'bottom', 'single'] as $part) {
                        foreach ($col[$part]['blocks'] ?? [] as $blk) {
                            $h += (int)($blk['h'] ?? 0);
                        }
                    }
                    $alturaKey = (string)$h;
                    $colsByAltura[$alturaKey] = ($colsByAltura[$alturaKey] ?? 0) + 1;
                }
                foreach ($colsByAltura as $alturaVal => $count) {
                    $key     = 'columna_' . $alturaVal;
                    $columna = $this->columnaRepo->findOneColumnaBySerieAndPlace($serie, $refPlacement, $tipo, $alturaVal);
                    $sizeCounts[$key] = $count;
                    $products[$key]   = $columna;
                    if ($columna) {
                        $productInfo[$key] = $this->btvApi->getProductInfo($columna->getReference(), $count);
                    }
                }
            } else {
                $columna = $this->columnaRepo->findOneColumnaBySerieAndPlace($serie, $refPlacement, $tipo);
                $sizeCounts['columna'] = $totalCols;
                $products['columna']   = $columna;
                if ($columna) {
                    $productInfo['columna'] = $this->btvApi->getProductInfo($columna->getReference(), $totalCols);
                }
            }
        }

        // Buzones integrados en columnas (home)
        foreach ($mailboxBlockCounts as $mbKey => $mbData) {
            $mbEntity = !empty($mbData['reference'])
                ? $this->mailboxRepo->findOneBy(['reference' => $mbData['reference']])
                : $this->mailboxRepo->findOneBy([
                    'agrupacion'  => false,
                    'electronico' => $mbData['electronico'],
                    'tarjetero'   => $mbData['tarjetero'],
                  ]);
            $sizeCounts[$mbKey] = $mbData['count'];
            $products[$mbKey]   = $mbEntity;
            if ($mbEntity) {
                $apiInfo = $this->btvApi->getProductInfo($mbEntity->getReference(), $mbData['count']) ?? [];
                if ($mbEntity->getDescripcion()) {
                    $apiInfo['descripcion'] = $mbEntity->getDescripcion();
                }
                $productInfo[$mbKey] = $apiInfo;
            }
        }

        // Control de acceso
        $controlRef = $payload['control'] ?? null;
        if ($controlRef) {
            $controlEntity = $this->controlRepo->findOneBy(['reference' => $controlRef]);
            $sizeCounts['control'] = 1;
            $products['control']   = $controlEntity ?? ['reference' => $controlRef];
            $apiInfo = $this->btvApi->getProductInfo($controlRef, 1);
            if (!is_array($apiInfo)) {
                $apiInfo = [];
            }
            $apiInfo['_descripcion'] = $controlEntity ? $controlEntity->getDescripcion() : null;
            $productInfo['control'] = $apiInfo;
        }

        // Bandejas: una por columna que tiene bloques tanto en top como en bottom (no aplica en Home)
        if ($tipo !== 'home') {
            $bandejaCount = 0;
            foreach ($allCols as $col) {
                $hasTop    = !empty($col['top']['blocks'] ?? []);
                $hasBottom = !empty($col['bottom']['blocks'] ?? []);
                if ($hasTop && $hasBottom) {
                    $bandejaCount++;
                }
            }
            if ($bandejaCount > 0) {
                $bandeja = $this->bandejaRepo->findOneBySerie($serie, $tipo);
                $sizeCounts['bandeja'] = $bandejaCount;
                $products['bandeja']   = $bandeja;
                if ($bandeja) {
                    $productInfo['bandeja'] = $this->btvApi->getProductInfo($bandeja->getReference(), $bandejaCount);
                }
            }
        }

        $roofGroups = !empty($groups) ? $groups : (!empty($payload['columns']) ? [$payload['columns']] : []);
        $roofCounts = [];
        foreach ($roofGroups as $groupCols) {
            if (!is_array($groupCols)) continue;
            $numCols = count($groupCols);
            if ($tipo === 'home') {
                // Home interior: tejado incluido en columnas, no se añade línea
                if ($placement === 'exterior') {
                    $roofCounts[1] = ($roofCounts[1] ?? 0) + $numCols;
                }
            } else {
                // Profesional: agrupar en pares de 2 + sueltos
                $pairs   = intdiv($numCols, 2);
                $singles = $numCols % 2;
                if ($pairs > 0)   { $roofCounts[2] = ($roofCounts[2] ?? 0) + $pairs; }
                if ($singles > 0) { $roofCounts[1] = ($roofCounts[1] ?? 0) + $singles; }
            }
        }
        foreach ($roofCounts as $numCols => $count) {
            $key  = 'tejado_' . $numCols;
            $roof = $this->roofRepo->findOneRoofBySerieAndPlaceAndColumns($serie, $placement, (string) $numCols, $tipo);
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
                        if ($btype === 'screen') {
                            continue;
                        }
                        if ($btype === 'mailbox' && empty($blk['electronico'])) {
                            continue;
                        }
                        $doorsInGroup++;
                    }
                }
            }
            $totalPlates += max(1, (int) ceil($doorsInGroup / $this->doorsPerPlate));
        }

        $extraPlates = $totalPlates - 1; // 1 placa ya incluida en periféricos
        if ($extraPlates > 0) {
            $sizeCounts['placa'] = $extraPlates;
            $products['placa']   = $this->placaReference;
            $productInfo['placa'] = $this->btvApi->getProductInfo($this->placaReference, $extraPlates);
        }

        // Instalación (referencia fija, 1 unidad siempre)

        // Colgadores: solo para instalación 'colgado' en Home
        if ($tipo === 'home' && ($payload['instalacion'] ?? '') === 'colgado' && $totalCols > 0) {
            $numColgadores = $totalCols <= 1 ? 1 : (int)(floor(($totalCols - 2) / 3) + 2);
            $sizeCounts['colgador'] = $numColgadores;
            $products['colgador']   = $this->colgadorReference;
            if ($this->colgadorReference !== '') {
                $productInfo['colgador'] = $this->btvApi->getProductInfo($this->colgadorReference, $numColgadores);
            }
        }

        // Grupo de buzones y envolvente (agrupación combinada) — no aplica en Home
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

                $envCriteria = ['tipo' => 'buzon', 'rango' => $rango];
                if ($tipo !== null) { $envCriteria['tipoConfig'] = $tipo; }
                $envolvente = $this->envolventeRepo->findOneBy($envCriteria)
                    ?? ($tipo !== null ? $this->envolventeRepo->findOneBy(['tipo' => 'buzon', 'rango' => $rango, 'tipoConfig' => null]) : null);
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

        // Color de puerta
        $colorDoorRef = $payload['colorDoorRef'] ?? null;
        if ($colorDoorRef) {
            $sizeCounts['color_door'] = 1;
            $products['color_door']   = $colorDoorRef;
            $apiInfo = $this->btvApi->getProductInfo($colorDoorRef, 1) ?? [];
            $productInfo['color_door'] = $apiInfo;
        }

        // Color de cuerpo (solo si es diferente al de puerta)
        $colorBodyRef = $payload['colorBodyRef'] ?? null;
        if ($colorBodyRef && $colorBodyRef !== $colorDoorRef) {
            $sizeCounts['color_body'] = 1;
            $products['color_body']   = $colorBodyRef;
            $apiInfo = $this->btvApi->getProductInfo($colorBodyRef, 1) ?? [];
            $productInfo['color_body'] = $apiInfo;
        }

        return [
            'products'    => $products,
            'productInfo' => $productInfo,
            'sizeCounts'  => $sizeCounts,
        ];
    }
}
