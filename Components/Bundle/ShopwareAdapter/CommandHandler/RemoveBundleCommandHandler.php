<?php

namespace PlentyConnector\Components\Bundle\ShopwareAdapter\CommandHandler;

use Doctrine\ORM\EntityManagerInterface;
use PlentyConnector\Components\Bundle\Command\RemoveBundleCommand;
use PlentyConnector\Components\Bundle\Helper\BundleHelper;
use PlentyConnector\Components\Bundle\TransferObject\Bundle;
use PlentyConnector\Connector\IdentityService\IdentityServiceInterface;
use PlentyConnector\Connector\ServiceBus\Command\CommandInterface;
use PlentyConnector\Connector\ServiceBus\CommandHandler\CommandHandlerInterface;
use PlentyConnector\Connector\ValueObject\Identity\Identity;
use Psr\Log\LoggerInterface;
use Shopware\CustomModels\Bundle\Bundle as BundleModel;
use Shopware\CustomModels\Bundle\Repository as BundleRepository;
use ShopwareAdapter\ShopwareAdapter;

/**
 * Class RemoveBundleCommandHandler.
 */
class RemoveBundleCommandHandler implements CommandHandlerInterface
{
    /**
     * @var IdentityServiceInterface
     */
    private $identityService;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var BundleHelper
     */
    private $bundleHelper;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * RemoveBundleCommandHandler constructor.
     *
     * @param IdentityServiceInterface $identityService
     * @param EntityManagerInterface   $entityManager
     * @param BundleHelper             $bundleHelper
     * @param LoggerInterface          $logger
     */
    public function __construct(
        IdentityServiceInterface $identityService,
        EntityManagerInterface $entityManager,
        BundleHelper $bundleHelper,
        LoggerInterface $logger
    ) {
        $this->identityService = $identityService;
        $this->entityManager = $entityManager;
        $this->bundleHelper = $bundleHelper;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(CommandInterface $command)
    {
        return $command instanceof RemoveBundleCommand &&
            $command->getAdapterName() === ShopwareAdapter::NAME;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Exception
     */
    public function handle(CommandInterface $command)
    {
        /**
         * @var RemoveBundleCommand $command
         */
        $identifier = $command->getObjectIdentifier();

        $this->bundleHelper->registerBundleModels();

        $identity = $this->identityService->findOneBy([
            'objectIdentifier' => (string) $identifier,
            'objectType' => Bundle::TYPE,
            'adapterName' => ShopwareAdapter::NAME,
        ]);

        if (null === $identity) {
            $this->logger->notice('no matching identity found', ['command' => $command]);

            return false;
        }

        /**
         * @var BundleRepository $repository
         */
        $repository = $this->entityManager->getRepository(BundleModel::class);

        $bundleModel = $repository->find($identity->getAdapterIdentifier());

        if (null === $bundleModel) {
            $this->logger->notice('identity removed but the object was not found', ['command' => $command]);

            return false;
        }

        $this->entityManager->persist($bundleModel);
        $this->entityManager->flush();

        $identities = $this->identityService->findBy([
            'objectIdentifier' => $identifier,
        ]);

        array_walk($identities, function (Identity $identity) {
            $this->identityService->remove($identity);
        });

        return true;
    }
}
