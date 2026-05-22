<?php

namespace App\Tests\Unit\Service;

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
use App\Service\BtvApiService;
use App\Service\ConfigurationService;
use PHPUnit\Framework\TestCase;

class ConfigurationServiceTest extends TestCase
{
    private ConfigurationService $service;

    protected function setUp(): void
    {
        $this->service = new ConfigurationService(
            $this->createStub(DoorRepository::class),
            $this->createStub(SideRepository::class),
            $this->createStub(RoofRepository::class),
            $this->createStub(EnvolventeRepository::class),
            $this->createStub(BandejaRepository::class),
            $this->createStub(BrazoRepository::class),
            $this->createStub(PataRepository::class),
            $this->createStub(ColumnaRepository::class),
            $this->createStub(ControlRepository::class),
            $this->createStub(MailboxRepository::class),
            $this->createStub(BtvApiService::class),
            '13180',
            '61456',
            '13406',
            16
        );
    }

    // ─── getDoorSizesByNumber ────────────────────────────────────────────────

    public function testGetDoorSizesByNumberEmptyPayload(): void
    {
        $this->assertSame([], $this->service->getDoorSizesByNumber([]));
    }

    public function testGetDoorSizesByNumberSingleColumn(): void
    {
        $payload = [
            'columns' => [[
                'single' => ['blocks' => [['h' => 10, 'type' => 'door'], ['h' => 20, 'type' => 'door']]],
                'top'    => ['blocks' => []],
                'bottom' => ['blocks' => []],
            ]],
        ];

        $result = $this->service->getDoorSizesByNumber($payload);

        $this->assertSame(['1' => '10', '2' => '20'], $result);
    }

    public function testGetDoorSizesByNumberSkipsScreensAndMailboxes(): void
    {
        $payload = [
            'columns' => [[
                'single' => ['blocks' => [
                    ['h' => 10, 'type' => 'door'],
                    ['h' => 20, 'type' => 'screen'],
                    ['h' => 10, 'type' => 'mailbox'],
                    ['h' => 30, 'type' => 'door'],
                ]],
                'top'    => ['blocks' => []],
                'bottom' => ['blocks' => []],
            ]],
        ];

        $result = $this->service->getDoorSizesByNumber($payload);

        $this->assertSame(['1' => '10', '2' => '30'], $result);
    }

    public function testGetDoorSizesByNumberWithGroups(): void
    {
        $col = [
            'single' => ['blocks' => [['h' => 40, 'type' => 'door']]],
            'top'    => ['blocks' => []],
            'bottom' => ['blocks' => []],
        ];
        $payload = ['groups' => [[$col], [$col]]];

        $result = $this->service->getDoorSizesByNumber($payload);

        $this->assertCount(2, $result);
        $this->assertSame('40', $result['1']);
        $this->assertSame('40', $result['2']);
    }

    public function testGetDoorSizesByNumberTopBottomBlocks(): void
    {
        $payload = [
            'columns' => [[
                'single' => ['blocks' => []],
                'top'    => ['blocks' => [['h' => 10, 'type' => 'door']]],
                'bottom' => ['blocks' => [['h' => 20, 'type' => 'door']]],
            ]],
        ];

        $result = $this->service->getDoorSizesByNumber($payload);

        $this->assertSame(['1' => '10', '2' => '20'], $result);
    }

    public function testGetDoorSizesByNumberLegacyIntegerBlock(): void
    {
        $payload = [
            'columns' => [[
                'single' => ['blocks' => [30]],
                'top'    => ['blocks' => []],
                'bottom' => ['blocks' => []],
            ]],
        ];

        $result = $this->service->getDoorSizesByNumber($payload);

        $this->assertSame(['1' => '30'], $result);
    }

    // ─── countDoorsInPayload ─────────────────────────────────────────────────

    public function testCountDoorsInPayloadEmpty(): void
    {
        $this->assertSame(0, $this->service->countDoorsInPayload([]));
    }

    public function testCountDoorsInPayload(): void
    {
        $payload = [
            'columns' => [[
                'single' => ['blocks' => [
                    ['h' => 10, 'type' => 'door'],
                    ['h' => 10, 'type' => 'door'],
                    ['h' => 10, 'type' => 'screen'],
                ]],
                'top'    => ['blocks' => []],
                'bottom' => ['blocks' => []],
            ]],
        ];

        $this->assertSame(2, $this->service->countDoorsInPayload($payload));
    }

    // ─── cleanAddons ─────────────────────────────────────────────────────────

    public function testCleanAddonsNoAddonsKey(): void
    {
        $payload = ['columns' => []];
        $this->assertSame($payload, $this->service->cleanAddons($payload, $payload));
    }

    public function testCleanAddonsKeepsAddonForExistingDoor(): void
    {
        $col = [
            'single' => ['blocks' => [['h' => 10, 'type' => 'door']]],
            'top'    => ['blocks' => []],
            'bottom' => ['blocks' => []],
        ];
        $payload = [
            'columns' => [$col],
            'addons'  => ['1' => ['socket' => true]],
        ];

        $result = $this->service->cleanAddons($payload, $payload);

        $this->assertArrayHasKey('1', $result['addons']);
    }

    public function testCleanAddonsRemovesAddonForRemovedDoor(): void
    {
        $colWith = [
            'single' => ['blocks' => [['h' => 10, 'type' => 'door']]],
            'top'    => ['blocks' => []],
            'bottom' => ['blocks' => []],
        ];
        $colWithout = [
            'single' => ['blocks' => []],
            'top'    => ['blocks' => []],
            'bottom' => ['blocks' => []],
        ];

        $old = ['columns' => [$colWith],    'addons' => ['1' => ['socket' => true]]];
        $new = ['columns' => [$colWithout], 'addons' => ['1' => ['socket' => true]]];

        $result = $this->service->cleanAddons($new, $old);

        $this->assertEmpty($result['addons']);
    }

    public function testCleanAddonsRemovesAddonWhenDoorChangedSize(): void
    {
        $colOld = [
            'single' => ['blocks' => [['h' => 10, 'type' => 'door']]],
            'top'    => ['blocks' => []],
            'bottom' => ['blocks' => []],
        ];
        $colNew = [
            'single' => ['blocks' => [['h' => 20, 'type' => 'door']]],
            'top'    => ['blocks' => []],
            'bottom' => ['blocks' => []],
        ];

        $old = ['columns' => [$colOld], 'addons' => ['1' => ['socket' => true]]];
        $new = ['columns' => [$colNew], 'addons' => ['1' => ['socket' => true]]];

        $result = $this->service->cleanAddons($new, $old);

        $this->assertEmpty($result['addons']);
    }

    // ─── prepareSummaryPayload ────────────────────────────────────────────────

    public function testPrepareSummaryPayloadAssignsDoorNumbers(): void
    {
        $payload = [
            'columns' => [[
                'single' => ['blocks' => [
                    ['h' => 10, 'type' => 'door'],
                    ['h' => 20, 'type' => 'door'],
                ]],
                'top'    => ['blocks' => []],
                'bottom' => ['blocks' => []],
            ]],
        ];

        $result   = $this->service->prepareSummaryPayload($payload);
        $prepared = $result['groups'][0][0]['single']['blocks_prepared'];

        $this->assertSame(1, $prepared[0]['doorNumber']);
        $this->assertSame(2, $prepared[1]['doorNumber']);
    }

    public function testPrepareSummaryPayloadScreenGetsNullDoorNumber(): void
    {
        $payload = [
            'columns' => [[
                'single' => ['blocks' => [['h' => 10, 'type' => 'screen']]],
                'top'    => ['blocks' => []],
                'bottom' => ['blocks' => []],
            ]],
        ];

        $result   = $this->service->prepareSummaryPayload($payload);
        $prepared = $result['groups'][0][0]['single']['blocks_prepared'];

        $this->assertNull($prepared[0]['doorNumber']);
        $this->assertTrue($prepared[0]['isScreen']);
    }

    public function testPrepareSummaryPayloadMechanicalMailboxGetsNullDoorNumber(): void
    {
        $payload = [
            'columns' => [[
                'single' => ['blocks' => [['h' => 10, 'type' => 'mailbox', 'electronico' => false]]],
                'top'    => ['blocks' => []],
                'bottom' => ['blocks' => []],
            ]],
        ];

        $result   = $this->service->prepareSummaryPayload($payload);
        $prepared = $result['groups'][0][0]['single']['blocks_prepared'];

        $this->assertNull($prepared[0]['doorNumber']);
        $this->assertTrue($prepared[0]['isMailbox']);
    }

    public function testPrepareSummaryPayloadElectronicMailboxGetsDoorNumber(): void
    {
        $payload = [
            'columns' => [[
                'single' => ['blocks' => [['h' => 10, 'type' => 'mailbox', 'electronico' => true]]],
                'top'    => ['blocks' => []],
                'bottom' => ['blocks' => []],
            ]],
        ];

        $result   = $this->service->prepareSummaryPayload($payload);
        $prepared = $result['groups'][0][0]['single']['blocks_prepared'];

        $this->assertSame(1, $prepared[0]['doorNumber']);
    }

    public function testPrepareSummaryPayloadPlateNumberIncreasesAfter16Doors(): void
    {
        $blocks = array_fill(0, 17, ['h' => 10, 'type' => 'door']);
        $payload = [
            'columns' => [[
                'single' => ['blocks' => $blocks],
                'top'    => ['blocks' => []],
                'bottom' => ['blocks' => []],
            ]],
        ];

        $result   = $this->service->prepareSummaryPayload($payload);
        $prepared = $result['groups'][0][0]['single']['blocks_prepared'];

        $this->assertSame(1, $prepared[0]['plateNumber']);
        $this->assertSame(1, $prepared[15]['plateNumber']);
        $this->assertSame(2, $prepared[16]['plateNumber']);
    }

    public function testPrepareSummaryPayloadAddonFlagsApplied(): void
    {
        $payload = [
            'columns' => [[
                'single' => ['blocks' => [['h' => 10, 'type' => 'door']]],
                'top'    => ['blocks' => []],
                'bottom' => ['blocks' => []],
            ]],
            'addons' => ['1' => ['socket' => true, 'usb' => false, 'methacrylate' => true]],
        ];

        $result   = $this->service->prepareSummaryPayload($payload);
        $prepared = $result['groups'][0][0]['single']['blocks_prepared'];

        $this->assertTrue($prepared[0]['socket']);
        $this->assertFalse($prepared[0]['usb']);
        $this->assertTrue($prepared[0]['methacrylate']);
    }

    // ─── buildProductTable — lógica de colgadores ────────────────────────────

    public function testBuildProductTableColgadorSingleColumn(): void
    {
        $btvMock = $this->createStub(BtvApiService::class);
        $btvMock->method('getProductInfo')->willReturn(null);

        $service = new ConfigurationService(
            $this->createStub(DoorRepository::class),
            $this->createStub(SideRepository::class),
            $this->createStub(RoofRepository::class),
            $this->createStub(EnvolventeRepository::class),
            $this->createStub(BandejaRepository::class),
            $this->createStub(BrazoRepository::class),
            $this->createStub(PataRepository::class),
            $this->createStub(ColumnaRepository::class),
            $this->createStub(ControlRepository::class),
            $this->createStub(MailboxRepository::class),
            $btvMock,
            '13180', '61456', '13406', 16
        );

        $col     = ['single' => ['blocks' => []], 'top' => ['blocks' => []], 'bottom' => ['blocks' => []]];
        $payload = [
            'type'       => 'home',
            'instalacion'=> 'colgado',
            'fondo'      => '300',
            'placement'  => 'interior',
            'columns'    => [$col],
        ];

        $result = $service->buildProductTable($payload);

        $this->assertSame(1, $result['sizeCounts']['colgador']);
    }

    public function testBuildProductTableColgadorFourColumns(): void
    {
        $btvMock = $this->createStub(BtvApiService::class);
        $btvMock->method('getProductInfo')->willReturn(null);

        $service = new ConfigurationService(
            $this->createStub(DoorRepository::class),
            $this->createStub(SideRepository::class),
            $this->createStub(RoofRepository::class),
            $this->createStub(EnvolventeRepository::class),
            $this->createStub(BandejaRepository::class),
            $this->createStub(BrazoRepository::class),
            $this->createStub(PataRepository::class),
            $this->createStub(ColumnaRepository::class),
            $this->createStub(ControlRepository::class),
            $this->createStub(MailboxRepository::class),
            $btvMock,
            '13180', '61456', '13406', 16
        );

        $col     = ['single' => ['blocks' => []], 'top' => ['blocks' => []], 'bottom' => ['blocks' => []]];
        $payload = [
            'type'        => 'home',
            'instalacion' => 'colgado',
            'fondo'       => '300',
            'placement'   => 'interior',
            'columns'     => [$col, $col, $col, $col], // 4 columnas → 2 colgadores
        ];

        $result = $service->buildProductTable($payload);

        // floor((4 - 2) / 3) + 2 = 0 + 2 = 2
        $this->assertSame(2, $result['sizeCounts']['colgador']);
    }

    public function testBuildProductTableNoColgadorForNonColgadoInstallation(): void
    {
        $btvMock = $this->createStub(BtvApiService::class);
        $btvMock->method('getProductInfo')->willReturn(null);

        $service = new ConfigurationService(
            $this->createStub(DoorRepository::class),
            $this->createStub(SideRepository::class),
            $this->createStub(RoofRepository::class),
            $this->createStub(EnvolventeRepository::class),
            $this->createStub(BandejaRepository::class),
            $this->createStub(BrazoRepository::class),
            $this->createStub(PataRepository::class),
            $this->createStub(ColumnaRepository::class),
            $this->createStub(ControlRepository::class),
            $this->createStub(MailboxRepository::class),
            $btvMock,
            '13180', '61456', '13406', 16
        );

        $col     = ['single' => ['blocks' => []], 'top' => ['blocks' => []], 'bottom' => ['blocks' => []]];
        $payload = [
            'type'        => 'home',
            'instalacion' => 'empotrado',
            'fondo'       => '300',
            'placement'   => 'interior',
            'columns'     => [$col],
        ];

        $result = $service->buildProductTable($payload);

        $this->assertArrayNotHasKey('colgador', $result['sizeCounts']);
    }

    // ─── buildProductTable — colores ─────────────────────────────────────────

    public function testBuildProductTableColorDoorAdded(): void
    {
        $btvMock = $this->createStub(BtvApiService::class);
        $btvMock->method('getProductInfo')->willReturn([]);

        $service = $this->makeServiceWithBtv($btvMock);

        $payload = [
            'fondo'       => '300',
            'colorDoorRef'=> '99001',
            'columns'     => [],
        ];

        $result = $service->buildProductTable($payload);

        $this->assertArrayHasKey('color_door', $result['products']);
        $this->assertSame('99001', $result['products']['color_door']);
        $this->assertSame(1, $result['sizeCounts']['color_door']);
    }

    public function testBuildProductTableColorBodySkippedWhenSameAsDoor(): void
    {
        $btvMock = $this->createStub(BtvApiService::class);
        $btvMock->method('getProductInfo')->willReturn([]);

        $service = $this->makeServiceWithBtv($btvMock);

        $payload = [
            'fondo'        => '300',
            'colorDoorRef' => '99001',
            'colorBodyRef' => '99001',
            'columns'      => [],
        ];

        $result = $service->buildProductTable($payload);

        $this->assertArrayNotHasKey('color_body', $result['products']);
    }

    public function testBuildProductTableColorBodyAddedWhenDifferent(): void
    {
        $btvMock = $this->createStub(BtvApiService::class);
        $btvMock->method('getProductInfo')->willReturn([]);

        $service = $this->makeServiceWithBtv($btvMock);

        $payload = [
            'fondo'        => '300',
            'colorDoorRef' => '99001',
            'colorBodyRef' => '99002',
            'columns'      => [],
        ];

        $result = $service->buildProductTable($payload);

        $this->assertArrayHasKey('color_body', $result['products']);
        $this->assertSame('99002', $result['products']['color_body']);
    }

    // ─── buildProductTable — agrupación de buzones ───────────────────────────

    public function testBuildProductTableMailboxGroupCountsActiveCells(): void
    {
        $btvMock = $this->createStub(BtvApiService::class);
        $btvMock->method('getProductInfo')->willReturn([]);

        $service = $this->makeServiceWithBtv($btvMock);

        $payload = [
            'fondo'              => '300',
            'agrupacion_combinada' => true,
            'mailboxGroup'       => [
                'reference'  => 'MB001',
                'rows'       => 2,
                'cols'       => 3,
                'cells'      => [true, true, true, true, true, false],
                'alto'       => 250,
                'ancho'      => 380,
                'descripcion'=> 'Buzón test',
            ],
            'columns' => [],
        ];

        $result = $service->buildProductTable($payload);

        $this->assertSame(5, $result['sizeCounts']['buzon_group']);
    }

    public function testBuildProductTableMailboxGroupEnvolventeRangoSmall(): void
    {
        $btvMock = $this->createStub(BtvApiService::class);
        $btvMock->method('getProductInfo')->willReturn([]);

        $envolventeRepo = $this->createMock(EnvolventeRepository::class);
        $envolventeRepo->method('findOneBy')->willReturn(null);

        $service = new ConfigurationService(
            $this->createStub(DoorRepository::class),
            $this->createStub(SideRepository::class),
            $this->createStub(RoofRepository::class),
            $envolventeRepo,
            $this->createStub(BandejaRepository::class),
            $this->createStub(BrazoRepository::class),
            $this->createStub(PataRepository::class),
            $this->createStub(ColumnaRepository::class),
            $this->createStub(ControlRepository::class),
            $this->createStub(MailboxRepository::class),
            $btvMock,
            '13180', '61456', '13406', 16
        );

        // 10 buzones → rango pequeño (<=15)
        $payload = [
            'fondo'              => '300',
            'agrupacion_combinada' => true,
            'mailboxGroup'       => [
                'reference'  => 'MB001',
                'rows'       => 2,
                'cols'       => 5,
                'cells'      => array_fill(0, 10, true),
                'alto'       => 250,
                'ancho'      => 380,
                'descripcion'=> '',
            ],
            'columns' => [],
        ];

        // Verificamos que se llama al repo con rango 'pequeño'
        $envolventeRepo->expects($this->atLeastOnce())
            ->method('findOneBy')
            ->with($this->callback(fn($c) => ($c['rango'] ?? '') === 'pequeño'));

        $service->buildProductTable($payload);
    }

    // ─── buildProductTable — placas extra ────────────────────────────────────

    public function testBuildProductTableNoExtraPlateFor16Doors(): void
    {
        $btvMock = $this->createStub(BtvApiService::class);
        $btvMock->method('getProductInfo')->willReturn(null);

        $doorRepo = $this->createStub(DoorRepository::class);
        $doorRepo->method('findOneDoorBySerieAndPlaceAndSizeAndMethacrylate')->willReturn(null);

        $service = new ConfigurationService(
            $doorRepo,
            $this->createStub(SideRepository::class),
            $this->createStub(RoofRepository::class),
            $this->createStub(EnvolventeRepository::class),
            $this->createStub(BandejaRepository::class),
            $this->createStub(BrazoRepository::class),
            $this->createStub(PataRepository::class),
            $this->createStub(ColumnaRepository::class),
            $this->createStub(ControlRepository::class),
            $this->createStub(MailboxRepository::class),
            $btvMock,
            '13180', '61456', '13406', 16
        );

        $blocks  = array_fill(0, 16, ['h' => 10, 'type' => 'door']);
        $payload = [
            'fondo'    => '300',
            'columns'  => [[
                'single' => ['blocks' => $blocks],
                'top'    => ['blocks' => []],
                'bottom' => ['blocks' => []],
            ]],
        ];

        $result = $service->buildProductTable($payload);

        $this->assertArrayNotHasKey('placa', $result['sizeCounts']);
    }

    public function testBuildProductTableExtraPlateFor17Doors(): void
    {
        $btvMock = $this->createStub(BtvApiService::class);
        $btvMock->method('getProductInfo')->willReturn(null);

        $doorRepo = $this->createStub(DoorRepository::class);
        $doorRepo->method('findOneDoorBySerieAndPlaceAndSizeAndMethacrylate')->willReturn(null);

        $service = new ConfigurationService(
            $doorRepo,
            $this->createStub(SideRepository::class),
            $this->createStub(RoofRepository::class),
            $this->createStub(EnvolventeRepository::class),
            $this->createStub(BandejaRepository::class),
            $this->createStub(BrazoRepository::class),
            $this->createStub(PataRepository::class),
            $this->createStub(ColumnaRepository::class),
            $this->createStub(ControlRepository::class),
            $this->createStub(MailboxRepository::class),
            $btvMock,
            '13180', '61456', '13406', 16
        );

        $blocks  = array_fill(0, 17, ['h' => 10, 'type' => 'door']);
        $payload = [
            'fondo'   => '300',
            'columns' => [[
                'single' => ['blocks' => $blocks],
                'top'    => ['blocks' => []],
                'bottom' => ['blocks' => []],
            ]],
        ];

        $result = $service->buildProductTable($payload);

        $this->assertSame(1, $result['sizeCounts']['placa']);
    }

    // ─── prepareSummaryPayload — formato top/bottom ───────────────────────────

    public function testPrepareSummaryPayloadTopBottomFormat(): void
    {
        $payload = [
            'columns' => [[
                'single' => ['blocks' => []],
                'top'    => ['blocks' => [['h' => 10, 'type' => 'door']]],
                'bottom' => ['blocks' => [['h' => 20, 'type' => 'door']]],
            ]],
        ];

        $result = $this->service->prepareSummaryPayload($payload);
        $col    = $result['groups'][0][0];

        $this->assertSame(1, $col['top']['blocks_prepared'][0]['doorNumber']);
        $this->assertSame(2, $col['bottom']['blocks_prepared'][0]['doorNumber']);
    }

    public function testPrepareSummaryPayloadTopBottomScreensGetNullDoorNumber(): void
    {
        $payload = [
            'columns' => [[
                'single' => ['blocks' => []],
                'top'    => ['blocks' => [['h' => 10, 'type' => 'screen']]],
                'bottom' => ['blocks' => [['h' => 20, 'type' => 'door']]],
            ]],
        ];

        $result = $this->service->prepareSummaryPayload($payload);
        $col    = $result['groups'][0][0];

        $this->assertNull($col['top']['blocks_prepared'][0]['doorNumber']);
        $this->assertSame(1, $col['bottom']['blocks_prepared'][0]['doorNumber']);
    }

    // ─── prepareSummaryPayload — numeración entre múltiples grupos ────────────

    public function testPrepareSummaryPayloadDoorNumberContinuesAcrossGroups(): void
    {
        $col = [
            'single' => ['blocks' => [['h' => 10, 'type' => 'door'], ['h' => 10, 'type' => 'door']]],
            'top'    => ['blocks' => []],
            'bottom' => ['blocks' => []],
        ];
        $payload = ['groups' => [[$col], [$col]]];

        $result = $this->service->prepareSummaryPayload($payload);

        $group1 = $result['groups'][0][0]['single']['blocks_prepared'];
        $group2 = $result['groups'][1][0]['single']['blocks_prepared'];

        $this->assertSame(1, $group1[0]['doorNumber']);
        $this->assertSame(2, $group1[1]['doorNumber']);
        // El grupo 2 reinicia el contador interno pero el global sigue
        $this->assertSame(1, $group2[0]['doorNumber']); // doorNumber es contador por grupo
        $this->assertSame(2, $group2[1]['doorNumber']);
    }

    public function testPrepareSummaryPayloadPlateNumberIncreasesAcrossGroups(): void
    {
        // Grupo 1: 16 puertas → ocupa placa 1
        // Grupo 2: 1 puerta → placa 2
        $blocks16 = array_fill(0, 16, ['h' => 10, 'type' => 'door']);
        $col16    = ['single' => ['blocks' => $blocks16], 'top' => ['blocks' => []], 'bottom' => ['blocks' => []]];
        $col1     = ['single' => ['blocks' => [['h' => 10, 'type' => 'door']]], 'top' => ['blocks' => []], 'bottom' => ['blocks' => []]];

        $payload = ['groups' => [[$col16], [$col1]]];

        $result = $this->service->prepareSummaryPayload($payload);

        $group2Prepared = $result['groups'][1][0]['single']['blocks_prepared'];
        $this->assertSame(2, $group2Prepared[0]['plateNumber']);
    }

    // ─── buildProductTable — lateral con entidad real ────────────────────────

    public function testBuildProductTableLateralFoundCallsBtvApi(): void
    {
        $side = (new \App\Entity\Side())->setReference('LAT-001')->setSerie('300')->setPlace('interior');

        $sideRepo = $this->createMock(SideRepository::class);
        $sideRepo->method('findOneSideBySerieAndPlace')->willReturn($side);

        $btvMock = $this->createMock(BtvApiService::class);
        $btvMock->expects($this->atLeastOnce())
                ->method('getProductInfo')
                ->with('LAT-001', $this->anything())
                ->willReturn(['PrecioVenta' => '50,00']);

        $service = new ConfigurationService(
            $this->createStub(DoorRepository::class),
            $sideRepo,
            $this->createStub(RoofRepository::class),
            $this->createStub(EnvolventeRepository::class),
            $this->createStub(BandejaRepository::class),
            $this->createStub(BrazoRepository::class),
            $this->createStub(PataRepository::class),
            $this->createStub(ColumnaRepository::class),
            $this->createStub(ControlRepository::class),
            $this->createStub(MailboxRepository::class),
            $btvMock,
            '13180', '61456', '13406', 16
        );

        $payload = ['fondo' => '300', 'placement' => 'interior', 'type' => 'profesional', 'columns' => []];
        $result  = $service->buildProductTable($payload);

        $this->assertSame($side, $result['products']['lateral']);
        $this->assertSame(['PrecioVenta' => '50,00'], $result['productInfo']['lateral']);
    }

    // ─── buildProductTable — tejados profesional (agrupación por pares) ───────

    public function testBuildProductTableRoofProfesionalPairLogic(): void
    {
        $roof2 = (new \App\Entity\Roof())->setReference('TEJ-2')->setSerie('300')->setPlace('interior')->setColumns('2');
        $roof1 = (new \App\Entity\Roof())->setReference('TEJ-1')->setSerie('300')->setPlace('interior')->setColumns('1');

        $roofRepo = $this->createMock(RoofRepository::class);
        $roofRepo->method('findOneRoofBySerieAndPlaceAndColumns')
                 ->willReturnCallback(fn($s, $p, $cols) => $cols === '2' ? $roof2 : $roof1);

        $btvMock = $this->createStub(BtvApiService::class);
        $btvMock->method('getProductInfo')->willReturn(null);

        $service = new ConfigurationService(
            $this->createStub(DoorRepository::class),
            $this->createStub(SideRepository::class),
            $roofRepo,
            $this->createStub(EnvolventeRepository::class),
            $this->createStub(BandejaRepository::class),
            $this->createStub(BrazoRepository::class),
            $this->createStub(PataRepository::class),
            $this->createStub(ColumnaRepository::class),
            $this->createStub(ControlRepository::class),
            $this->createStub(MailboxRepository::class),
            $btvMock,
            '13180', '61456', '13406', 16
        );

        $col     = ['single' => ['blocks' => []], 'top' => ['blocks' => []], 'bottom' => ['blocks' => []]];
        // 5 columnas → 2 pares (tejado_2 × 2) + 1 suelta (tejado_1 × 1)
        $payload = [
            'fondo'     => '300',
            'placement' => 'interior',
            'type'      => 'profesional',
            'columns'   => [$col, $col, $col, $col, $col],
        ];

        $result = $service->buildProductTable($payload);

        $this->assertSame(2, $result['sizeCounts']['tejado_2']);
        $this->assertSame(1, $result['sizeCounts']['tejado_1']);
        $this->assertSame($roof2, $result['products']['tejado_2']);
        $this->assertSame($roof1, $result['products']['tejado_1']);
    }

    public function testBuildProductTableRoofProfesionalEvenColumns(): void
    {
        $roof2 = (new \App\Entity\Roof())->setReference('TEJ-2')->setSerie('300')->setPlace('interior')->setColumns('2');

        $roofRepo = $this->createMock(RoofRepository::class);
        $roofRepo->method('findOneRoofBySerieAndPlaceAndColumns')->willReturn($roof2);

        $btvMock = $this->createStub(BtvApiService::class);
        $btvMock->method('getProductInfo')->willReturn(null);

        $service = new ConfigurationService(
            $this->createStub(DoorRepository::class),
            $this->createStub(SideRepository::class),
            $roofRepo,
            $this->createStub(EnvolventeRepository::class),
            $this->createStub(BandejaRepository::class),
            $this->createStub(BrazoRepository::class),
            $this->createStub(PataRepository::class),
            $this->createStub(ColumnaRepository::class),
            $this->createStub(ControlRepository::class),
            $this->createStub(MailboxRepository::class),
            $btvMock,
            '13180', '61456', '13406', 16
        );

        $col     = ['single' => ['blocks' => []], 'top' => ['blocks' => []], 'bottom' => ['blocks' => []]];
        // 4 columnas → 2 pares, sin sueltas
        $payload = [
            'fondo'     => '300',
            'placement' => 'interior',
            'type'      => 'profesional',
            'columns'   => [$col, $col, $col, $col],
        ];

        $result = $service->buildProductTable($payload);

        $this->assertSame(2, $result['sizeCounts']['tejado_2']);
        $this->assertArrayNotHasKey('tejado_1', $result['sizeCounts']);
    }

    // ─── buildProductTable — brazos ───────────────────────────────────────────

    public function testBuildProductTableBrazosAddedForSoporteSuelo(): void
    {
        $brazo = (new \App\Entity\Brazo())->setReference('BRZ-001')->setAltura('8H');

        $brazoRepo = $this->createMock(BrazoRepository::class);
        $brazoRepo->method('findOneBy')->willReturn($brazo);

        $btvMock = $this->createMock(BtvApiService::class);
        $btvMock->expects($this->atLeastOnce())
                ->method('getProductInfo')
                ->with('BRZ-001', $this->anything())
                ->willReturn(['PrecioVenta' => '120,00']);

        $service = new ConfigurationService(
            $this->createStub(DoorRepository::class),
            $this->createStub(SideRepository::class),
            $this->createStub(RoofRepository::class),
            $this->createStub(EnvolventeRepository::class),
            $this->createStub(BandejaRepository::class),
            $brazoRepo,
            $this->createStub(PataRepository::class),
            $this->createStub(ColumnaRepository::class),
            $this->createStub(ControlRepository::class),
            $this->createStub(MailboxRepository::class),
            $btvMock,
            '13180', '61456', '13406', 16
        );

        // columna con 8 bloques de H10 = H80
        $blocks  = array_fill(0, 8, ['h' => 10, 'type' => 'door']);
        $col     = ['single' => ['blocks' => $blocks], 'top' => ['blocks' => []], 'bottom' => ['blocks' => []]];
        $payload = [
            'fondo'       => '300',
            'type'        => 'home',
            'instalacion' => 'soporte_suelo',
            'bracketType' => 'brazos',
            'groups'      => [[$col]],
        ];

        $result = $service->buildProductTable($payload);

        $this->assertArrayHasKey('brazo_80', $result['sizeCounts']);
        $this->assertSame(1, $result['sizeCounts']['brazo_80']);
        $this->assertSame($brazo, $result['products']['brazo_80']);
    }

    public function testBuildProductTableBrazosNotAddedForOtherInstallation(): void
    {
        $btvMock = $this->createStub(BtvApiService::class);
        $btvMock->method('getProductInfo')->willReturn(null);

        $col     = ['single' => ['blocks' => []], 'top' => ['blocks' => []], 'bottom' => ['blocks' => []]];
        $payload = [
            'fondo'       => '300',
            'type'        => 'home',
            'instalacion' => 'empotrado',
            'bracketType' => 'brazos',
            'groups'      => [[$col]],
        ];

        $result = $this->makeServiceWithBtv($btvMock)->buildProductTable($payload);

        $brazos = array_filter(array_keys($result['sizeCounts']), fn($k) => str_starts_with($k, 'brazo_'));
        $this->assertEmpty($brazos);
    }

    // ─── buildProductTable — patas ────────────────────────────────────────────

    public function testBuildProductTablePatasAddedPerGroup(): void
    {
        $pata = (new \App\Entity\Pata())->setReference('PAT-002')->setNumColumnas(2);

        $pataRepo = $this->createMock(PataRepository::class);
        $pataRepo->method('findOneBy')->willReturn($pata);

        $btvMock = $this->createMock(BtvApiService::class);
        $btvMock->method('getProductInfo')->willReturn(null);

        $service = new ConfigurationService(
            $this->createStub(DoorRepository::class),
            $this->createStub(SideRepository::class),
            $this->createStub(RoofRepository::class),
            $this->createStub(EnvolventeRepository::class),
            $this->createStub(BandejaRepository::class),
            $this->createStub(BrazoRepository::class),
            $pataRepo,
            $this->createStub(ColumnaRepository::class),
            $this->createStub(ControlRepository::class),
            $this->createStub(MailboxRepository::class),
            $btvMock,
            '13180', '61456', '13406', 16
        );

        $col     = ['single' => ['blocks' => []], 'top' => ['blocks' => []], 'bottom' => ['blocks' => []]];
        // 2 grupos de 2 columnas → 2 kits de patas
        $payload = [
            'fondo'       => '300',
            'type'        => 'home',
            'instalacion' => 'soporte_suelo',
            'bracketType' => 'patas',
            'groups'      => [[$col, $col], [$col, $col]],
        ];

        $result = $service->buildProductTable($payload);

        $this->assertSame(2, $result['sizeCounts']['pata_2']);
        $this->assertSame($pata, $result['products']['pata_2']);
    }

    // ─── buildProductTable — bandejas ─────────────────────────────────────────

    public function testBuildProductTableBandejaAddedForTopBottomColumns(): void
    {
        $bandeja = (new \App\Entity\Bandeja())->setReference('BAN-300')->setSerie('300');

        $bandejaRepo = $this->createMock(BandejaRepository::class);
        $bandejaRepo->method('findOneBySerie')->willReturn($bandeja);

        $btvMock = $this->createStub(BtvApiService::class);
        $btvMock->method('getProductInfo')->willReturn(null);

        $service = new ConfigurationService(
            $this->createStub(DoorRepository::class),
            $this->createStub(SideRepository::class),
            $this->createStub(RoofRepository::class),
            $this->createStub(EnvolventeRepository::class),
            $bandejaRepo,
            $this->createStub(BrazoRepository::class),
            $this->createStub(PataRepository::class),
            $this->createStub(ColumnaRepository::class),
            $this->createStub(ControlRepository::class),
            $this->createStub(MailboxRepository::class),
            $btvMock,
            '13180', '61456', '13406', 16
        );

        $colWithTopBottom = [
            'single' => ['blocks' => []],
            'top'    => ['blocks' => [['h' => 10, 'type' => 'door']]],
            'bottom' => ['blocks' => [['h' => 10, 'type' => 'door']]],
        ];
        $colOnlyTop = [
            'single' => ['blocks' => []],
            'top'    => ['blocks' => [['h' => 10, 'type' => 'door']]],
            'bottom' => ['blocks' => []],
        ];

        $payload = [
            'fondo'     => '300',
            'type'      => 'profesional',
            'placement' => 'interior',
            'columns'   => [$colWithTopBottom, $colWithTopBottom, $colOnlyTop],
        ];

        $result = $service->buildProductTable($payload);

        // 2 columnas con top+bottom → 2 bandejas; la 3ª (solo top) no cuenta
        $this->assertSame(2, $result['sizeCounts']['bandeja']);
        $this->assertSame($bandeja, $result['products']['bandeja']);
    }

    public function testBuildProductTableBandejaNotAddedForHome(): void
    {
        $bandejaRepo = $this->createMock(BandejaRepository::class);
        $bandejaRepo->expects($this->never())->method('findOneBySerie');

        $btvMock = $this->createStub(BtvApiService::class);
        $btvMock->method('getProductInfo')->willReturn(null);

        $service = new ConfigurationService(
            $this->createStub(DoorRepository::class),
            $this->createStub(SideRepository::class),
            $this->createStub(RoofRepository::class),
            $this->createStub(EnvolventeRepository::class),
            $bandejaRepo,
            $this->createStub(BrazoRepository::class),
            $this->createStub(PataRepository::class),
            $this->createStub(ColumnaRepository::class),
            $this->createStub(ControlRepository::class),
            $this->createStub(MailboxRepository::class),
            $btvMock,
            '13180', '61456', '13406', 16
        );

        $col     = ['single' => ['blocks' => []], 'top' => ['blocks' => [['h' => 10, 'type' => 'door']]], 'bottom' => ['blocks' => [['h' => 10, 'type' => 'door']]]];
        $payload = ['fondo' => '300', 'type' => 'home', 'columns' => [$col]];

        $result = $service->buildProductTable($payload);

        $this->assertArrayNotHasKey('bandeja', $result['sizeCounts']);
    }

    // ─── buildProductTable — columnas home agrupadas por altura ──────────────

    public function testBuildProductTableHomeColumnasGroupedByHeight(): void
    {
        $col40 = (new \App\Entity\Columna())->setReference('COL-40')->setSerie('300')->setAltura('40');
        $col80 = (new \App\Entity\Columna())->setReference('COL-80')->setSerie('300')->setAltura('80');

        $columnaRepo = $this->createMock(ColumnaRepository::class);
        $columnaRepo->method('findOneColumnaBySerieAndPlace')
                    ->willReturnCallback(fn($s, $p, $t, $h) => $h === '40' ? $col40 : $col80);

        $btvMock = $this->createStub(BtvApiService::class);
        $btvMock->method('getProductInfo')->willReturn(null);

        $service = new ConfigurationService(
            $this->createStub(DoorRepository::class),
            $this->createStub(SideRepository::class),
            $this->createStub(RoofRepository::class),
            $this->createStub(EnvolventeRepository::class),
            $this->createStub(BandejaRepository::class),
            $this->createStub(BrazoRepository::class),
            $this->createStub(PataRepository::class),
            $columnaRepo,
            $this->createStub(ControlRepository::class),
            $this->createStub(MailboxRepository::class),
            $btvMock,
            '13180', '61456', '13406', 16
        );

        // 2 columnas H40, 1 columna H80
        $colH40 = ['single' => ['blocks' => [['h' => 40, 'type' => 'door']]], 'top' => ['blocks' => []], 'bottom' => ['blocks' => []]];
        $colH80 = ['single' => ['blocks' => [['h' => 80, 'type' => 'door']]], 'top' => ['blocks' => []], 'bottom' => ['blocks' => []]];

        $payload = [
            'fondo'     => '300',
            'type'      => 'home',
            'placement' => 'interior',
            'groups'    => [[$colH40, $colH40, $colH80]],
        ];

        $result = $service->buildProductTable($payload);

        $this->assertSame(2, $result['sizeCounts']['columna_40']);
        $this->assertSame(1, $result['sizeCounts']['columna_80']);
        $this->assertSame($col40, $result['products']['columna_40']);
        $this->assertSame($col80, $result['products']['columna_80']);
    }

    // ─── buildProductTable — buzón por referencia específica ─────────────────

    public function testBuildProductTableMailboxByReferenceUsesRef(): void
    {
        $mbEntity = (new \App\Entity\Mailbox())
            ->setReference('70778')
            ->setDescripcion('Buzón Viena')
            ->setAlto('125')
            ->setAncho('380')
            ->setFondo('300');

        $mailboxRepo = $this->createMock(MailboxRepository::class);
        $mailboxRepo->expects($this->once())
                    ->method('findOneBy')
                    ->with(['reference' => '70778'])
                    ->willReturn($mbEntity);

        $btvMock = $this->createStub(BtvApiService::class);
        $btvMock->method('getProductInfo')->willReturn(['PrecioVenta' => '45,00']);

        $service = new ConfigurationService(
            $this->createStub(DoorRepository::class),
            $this->createStub(SideRepository::class),
            $this->createStub(RoofRepository::class),
            $this->createStub(EnvolventeRepository::class),
            $this->createStub(BandejaRepository::class),
            $this->createStub(BrazoRepository::class),
            $this->createStub(PataRepository::class),
            $this->createStub(ColumnaRepository::class),
            $this->createStub(ControlRepository::class),
            $mailboxRepo,
            $btvMock,
            '13180', '61456', '13406', 16
        );

        $col = [
            'single' => ['blocks' => [['h' => 10, 'type' => 'mailbox', 'electronico' => false, 'tarjetero' => true, 'reference' => '70778']]],
            'top'    => ['blocks' => []],
            'bottom' => ['blocks' => []],
        ];

        $result = $service->buildProductTable(['fondo' => '300', 'columns' => [$col]]);

        $this->assertArrayHasKey('mailbox_col_ref_70778', $result['sizeCounts']);
        $this->assertSame(1, $result['sizeCounts']['mailbox_col_ref_70778']);
        $this->assertSame('Buzón Viena', $result['productInfo']['mailbox_col_ref_70778']['descripcion']);
    }

    // ─── buildProductTable — puerta con entidad real y precio ────────────────

    public function testBuildProductTableDoorFoundAndPriceRetrieved(): void
    {
        $door = (new \App\Entity\Door())
            ->setReference('PUE-001')
            ->setSerie('300')
            ->setPlace('interior')
            ->setSize('10');

        $doorRepo = $this->createMock(DoorRepository::class);
        $doorRepo->method('findOneDoorBySerieAndPlaceAndSizeAndMethacrylate')->willReturn($door);

        $btvMock = $this->createMock(BtvApiService::class);
        $btvMock->expects($this->atLeastOnce())
                ->method('getProductInfo')
                ->with('PUE-001', 2)
                ->willReturn(['PrecioVenta' => '30,00', 'CodigoProducto' => 'PUE-001']);

        $service = new ConfigurationService(
            $doorRepo,
            $this->createStub(SideRepository::class),
            $this->createStub(RoofRepository::class),
            $this->createStub(EnvolventeRepository::class),
            $this->createStub(BandejaRepository::class),
            $this->createStub(BrazoRepository::class),
            $this->createStub(PataRepository::class),
            $this->createStub(ColumnaRepository::class),
            $this->createStub(ControlRepository::class),
            $this->createStub(MailboxRepository::class),
            $btvMock,
            '13180', '61456', '13406', 16
        );

        $col = [
            'single' => ['blocks' => [['h' => 10, 'type' => 'door'], ['h' => 10, 'type' => 'door']]],
            'top'    => ['blocks' => []],
            'bottom' => ['blocks' => []],
        ];

        $result = $service->buildProductTable([
            'fondo'     => '300',
            'placement' => 'interior',
            'columns'   => [$col],
        ]);

        $this->assertSame($door, $result['products']['10']);
        $this->assertSame(2, $result['sizeCounts']['10']);
        $this->assertSame('30,00', $result['productInfo']['10']['PrecioVenta']);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function makeServiceWithBtv(BtvApiService $btvMock): ConfigurationService
    {
        return new ConfigurationService(
            $this->createStub(DoorRepository::class),
            $this->createStub(SideRepository::class),
            $this->createStub(RoofRepository::class),
            $this->createStub(EnvolventeRepository::class),
            $this->createStub(BandejaRepository::class),
            $this->createStub(BrazoRepository::class),
            $this->createStub(PataRepository::class),
            $this->createStub(ColumnaRepository::class),
            $this->createStub(ControlRepository::class),
            $this->createStub(MailboxRepository::class),
            $btvMock,
            '13180', '61456', '13406', 16
        );
    }
}
