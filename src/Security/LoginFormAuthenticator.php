<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\BtvApiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class LoginFormAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    // Un código de distribuidor es su usuario de BTV: 4-5 dígitos.
    private const DISTRIBUTOR_CODE_PATTERN = '/^\d{4,5}$/';

    private UrlGeneratorInterface $urlGenerator;
    private BtvApiService $btvApi;
    private UserRepository $userRepository;
    private EntityManagerInterface $em;

    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        BtvApiService $btvApi,
        UserRepository $userRepository,
        EntityManagerInterface $em
    ) {
        $this->urlGenerator = $urlGenerator;
        $this->btvApi = $btvApi;
        $this->userRepository = $userRepository;
        $this->em = $em;
    }

    public function authenticate(Request $request): Passport
    {
        $identifier = trim((string) $request->request->get('identifier', ''));
        $password = (string) $request->request->get('password', '');

        $request->getSession()->set(Security::LAST_USERNAME, $identifier);

        $csrfBadge = new CsrfTokenBadge('authenticate', $request->request->get('_csrf_token'));

        if (preg_match(self::DISTRIBUTOR_CODE_PATTERN, $identifier)) {
            // Distribuidor: la contraseña no se comprueba aquí contra
            // nuestra BD (no la guardamos), se valida directamente contra
            // la API de BTV dentro del "loader" de abajo. Por eso es un
            // SelfValidatingPassport en vez de llevar PasswordCredentials.
            return new SelfValidatingPassport(
                new UserBadge($identifier, function (string $code) use ($password) {
                    return $this->loadOrCreateDistributor($code, $password);
                }),
                [$csrfBadge]
            );
        }

        return new Passport(
            new UserBadge($identifier),
            new PasswordCredentials($password),
            [$csrfBadge]
        );
    }

    /**
     * Valida el código+contraseña contra BTV; si es correcto, busca (o crea
     * la primera vez) el usuario local correspondiente, solo para poder
     * relacionar proyectos/configuraciones con él. Nunca se guarda su
     * contraseña real.
     */
    private function loadOrCreateDistributor(string $code, string $password): User
    {
        if (!$this->btvApi->verifyCredentials($code, $password)) {
            throw new CustomUserMessageAuthenticationException('Código o contraseña incorrectos.');
        }

        $user = $this->userRepository->findOneBy(['distributorCode' => $code]);
        if ($user) {
            return $user;
        }

        $user = new User();
        $user->setDistributorCode($code);
        // email/name son únicos y obligatorios en la entidad: se rellenan
        // con un valor derivado del código (nunca se usan para loguear:
        // el login de distribuidor siempre pasa por distributorCode).
        $user->setEmail($code . '@distribuidor.kuik.local');
        $user->setName($code);
        $user->setRoles(['ROLE_DISTRIBUIDOR']);
        $user->setPassword(null);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->urlGenerator->generate('projects_list'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
