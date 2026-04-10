<?php

namespace App\Controller\Admin;

use App\Entity\Columna;
use App\Entity\Door;
use App\Entity\Mailbox;
use App\Entity\Roof;
use App\Entity\Side;
use App\Repository\ColumnaRepository;
use App\Repository\DoorRepository;
use App\Repository\MailboxRepository;
use App\Repository\RoofRepository;
use App\Repository\SideRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin/import", name="admin_import_")
 */
class ImportAdminController extends AbstractController
{
    /**
     * @Route("/", name="index", methods={"GET"})
     */
    public function index(): Response
    {
        return $this->render('admin/import/index.html.twig');
    }

    /**
     * @Route("/upload", name="upload", methods={"POST"})
     */
    public function upload(
        Request $request,
        EntityManagerInterface $em,
        DoorRepository $doorRepo,
        SideRepository $sideRepo,
        RoofRepository $roofRepo,
        ColumnaRepository $columnaRepo,
        MailboxRepository $mailboxRepo
    ): Response {
        if (!$this->isCsrfTokenValid('import_excel', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido');
        }

        $file = $request->files->get('excel_file');

        if (!$file) {
            $this->addFlash('error', 'No se ha seleccionado ningún archivo.');
            return $this->redirectToRoute('admin_import_index');
        }

        $allowed = ['xlsx', 'xls', 'ods'];
        $ext = strtolower($file->getClientOriginalExtension());

        if (!in_array($ext, $allowed, true)) {
            $this->addFlash('error', 'Formato no válido. Usa .xlsx, .xls o .ods');
            return $this->redirectToRoute('admin_import_index');
        }

        try {
            $spreadsheet = IOFactory::load($file->getPathname());
        } catch (\Exception $e) {
            $this->addFlash('error', 'No se pudo leer el archivo: ' . $e->getMessage());
            return $this->redirectToRoute('admin_import_index');
        }

        $stats     = ['door' => 0, 'side' => 0, 'roof' => 0, 'columna' => 0, 'mailbox' => 0];
        $duplicates = [];

        // ── Hoja DOOR ──────────────────────────────────────────────
        // Columnas: reference | serie | place | size | methacrylate
        if ($spreadsheet->getSheetByName('door')) {
            $sheet = $spreadsheet->getSheetByName('door');
            foreach ($sheet->getRowIterator(2) as $row) {
                $cells = $this->rowToArray($sheet, $row->getRowIndex(), 5);
                [$reference, $serie, $place, $size, $meth] = $cells;

                if (empty($reference) || empty($serie) || empty($place) || empty($size)) {
                    continue;
                }

                $methacrylate = filter_var($meth, FILTER_VALIDATE_BOOLEAN);

                $existing = $doorRepo->findOneDoorBySerieAndPlaceAndSizeAndMethacrylate(
                    $serie, $place, $size, $methacrylate
                );

                if ($existing) {
                    $duplicates[] = sprintf('%s (puerta)', $reference);
                    continue;
                }

                $door = (new Door())
                    ->setReference($reference)
                    ->setSerie($serie)
                    ->setPlace($place)
                    ->setSize($size)
                    ->setMethacrylate($methacrylate);

                $em->persist($door);
                $stats['door']++;
            }
        }

        // ── Hoja SIDE ──────────────────────────────────────────────
        // Columnas: reference | serie | place
        if ($spreadsheet->getSheetByName('side')) {
            $sheet = $spreadsheet->getSheetByName('side');
            foreach ($sheet->getRowIterator(2) as $row) {
                $cells = $this->rowToArray($sheet, $row->getRowIndex(), 3);
                [$reference, $serie, $place] = $cells;

                if (empty($reference) || empty($serie) || empty($place)) {
                    continue;
                }

                $existing = $sideRepo->findOneSideBySerieAndPlace($serie, $place);

                if ($existing) {
                    $duplicates[] = sprintf('%s (lateral)', $reference);
                    continue;
                }

                $side = (new Side())
                    ->setReference($reference)
                    ->setSerie($serie)
                    ->setPlace($place);

                $em->persist($side);
                $stats['side']++;
            }
        }

        // ── Hoja ROOF ──────────────────────────────────────────────
        // Columnas: reference | serie | place | columns
        if ($spreadsheet->getSheetByName('roof')) {
            $sheet = $spreadsheet->getSheetByName('roof');
            foreach ($sheet->getRowIterator(2) as $row) {
                $cells = $this->rowToArray($sheet, $row->getRowIndex(), 4);
                [$reference, $serie, $place, $columns] = $cells;

                if (empty($reference) || empty($serie) || empty($place) || empty($columns)) {
                    continue;
                }

                $existing = $roofRepo->findOneRoofBySerieAndPlaceAndColumns($serie, $place, (string) $columns);

                if ($existing) {
                    $duplicates[] = sprintf('%s (tejado)', $reference);
                    continue;
                }

                $roof = (new Roof())
                    ->setReference($reference)
                    ->setSerie($serie)
                    ->setPlace($place)
                    ->setColumns((string) $columns);

                $em->persist($roof);
                $stats['roof']++;
            }
        }

        // ── Hoja COLUMNA ───────────────────────────────────────────
        // Columnas: reference | serie | place
        if ($spreadsheet->getSheetByName('columna')) {
            $sheet = $spreadsheet->getSheetByName('columna');
            foreach ($sheet->getRowIterator(2) as $row) {
                $cells = $this->rowToArray($sheet, $row->getRowIndex(), 3);
                [$reference, $serie, $place] = $cells;

                if (empty($reference) || empty($serie) || empty($place)) {
                    continue;
                }

                $existing = $columnaRepo->findOneColumnaBySerieAndPlace($serie, $place);

                if ($existing) {
                    $duplicates[] = sprintf('%s (columna)', $reference);
                    continue;
                }

                $columna = (new Columna())
                    ->setReference($reference)
                    ->setSerie($serie)
                    ->setPlace($place);

                $em->persist($columna);
                $stats['columna']++;
            }
        }

        // ── Hoja MAILBOX ────────────────────────────────────────────
        // Columnas: reference | alto | ancho | fondo
        if ($spreadsheet->getSheetByName('mailbox')) {
            $sheet = $spreadsheet->getSheetByName('mailbox');
            foreach ($sheet->getRowIterator(2) as $row) {
                $cells = $this->rowToArray($sheet, $row->getRowIndex(), 4);
                [$reference, $alto, $ancho, $fondo] = $cells;

                if (empty($reference) || empty($alto) || empty($ancho) || empty($fondo)) {
                    continue;
                }

                $existing = $mailboxRepo->findOneMailboxByDimensions($alto, $ancho, $fondo);

                if ($existing) {
                    $duplicates[] = sprintf('%s (buzón)', $reference);
                    continue;
                }

                $em->persist((new Mailbox())
                    ->setReference($reference)
                    ->setAlto($alto)
                    ->setAncho($ancho)
                    ->setFondo($fondo));

                $stats['mailbox']++;
            }
        }

        $em->flush();

        $this->addFlash('success', sprintf(
            'Importación completada: %d puertas, %d laterales, %d tejados, %d columnas, %d buzones.',
            $stats['door'],
            $stats['side'],
            $stats['roof'],
            $stats['columna'],
            $stats['mailbox']
        ));

        if (!empty($duplicates)) {
            $this->addFlash('duplicates', implode('||', $duplicates));
        }

        return $this->redirectToRoute('admin_import_index');
    }

    private function rowToArray($sheet, int $rowIndex, int $cols): array
    {
        $result = [];
        for ($col = 1; $col <= $cols; $col++) {
            $val = $sheet->getCellByColumnAndRow($col, $rowIndex)->getValue();
            $result[] = $val !== null ? trim((string) $val) : '';
        }
        return $result;
    }
}
