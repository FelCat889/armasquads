<?php
namespace Frontend\Squads\Controller;

use Frontend\Squads\Entity\Squad;
use Frontend\Squads\Form\Squad as SquadForm;
use Frontend\Application\Controller\AbstractDoctrineController;
use Frontend\Application\Controller\AbstractFrontendController;
use Zend\View\Model\ViewModel;

class SquadsController extends AbstractFrontendController
{

    public function indexAction(){
        $squadRepo = $this->getEntityManager()->getRepository('Frontend\Squads\Entity\Squad');
        $userSquads = $squadRepo->findBy(array('user' => $this->identity() ), array('id' => 'desc'));

        $viewModel = new ViewModel();
        $viewModel->setTemplate('/squads/index.phtml');
        $viewModel->setVariable('squads', $userSquads);
        return $viewModel;
    }

    public function downloadAction()
    {
        $squadID = $this->params('id', 0);

        $squadRepo = $this->getEntityManager()->getRepository('Frontend\Squads\Entity\Squad');

        /** @var Squad $squadEntity */
        $squadEntity = $squadRepo->findOneBy(array(
            'user' => $this->identity(),
            'id' => $squadID
        ));

        if( ! $squadEntity )
        {
            $this->flashMessenger()->addErrorMessage('Squad not found');
            return $this->redirect('frontend/user/squads');
        }

        $fileName = 'squad_file_pack_armasquads_' . $squadID;
        $zipTmpPath = tempnam(ini_get('upload_tmp_dir'), $fileName);

        $zip = new \ZipArchive();
        $zip->open($zipTmpPath, \ZipArchive::CHECKCONS);

        $squadXMLController = $this->forward()->dispatch('Frontend\Squads\Controller\SquadXml', [
            'action' => 'squadFile',
            'id'   => $squadEntity->getPrivateID(),
        ]);
        $squadXMLResponse = $squadXMLController->getContent();

        if( !$zip || !$squadXMLResponse)
        {
            $this->flashMessenger()->addErrorMessage('Squad Package Download currently not possible');
            return $this->redirect('frontend/user/squads');
        }

        $zip->addFromString('squad.xml', $squadXMLResponse);

        if( $squadEntity->getSquadLogoPaa() ) {
            $zip->addFile(ROOT_PATH . $squadEntity->getSquadLogoPaa(), basename($squadEntity->getSquadLogoPaa()));
        }

        $zip->addFromString('squad.dtd',file_get_contents(realpath(__DIR__ . '/../../../../view/squads/xml/').'/squad.dtd'));
        //$zip->addFromString('squad.xsl',file_get_contents(realpath(__DIR__ . '/../../../../view/squads/xml/').'/squad.xsl'));
        $zip->close();

        header('Content-Type: application/octet-stream');
        header("Content-Transfer-Encoding: Binary");
        header("Content-disposition: attachment; filename=\"" . basename($fileName) . ".zip\"");

        readfile($zipTmpPath);
        sleep(1);
        @unlink($zipTmpPath);
        die();
    }

    public function deleteAction()
    {
        $squadID = $this->params('id', 0);

        $squadRepo = $this->getEntityManager()->getRepository('Frontend\Squads\Entity\Squad');
        $squadEntity = $squadRepo->findOneBy(array(
            'user' => $this->identity(),
            'id' => $squadID
        ));

        if( ! $squadEntity )
        {
            $this->flashMessenger()->addErrorMessage('Squad not found');
        }
        else
        {
            // delete logos
            if( $squadEntity->getLogo() )
            {
                $squadImageService = $this->getServiceLocator()->get('SquadImageService');
                $squadImageService->deleteLogo(
                    $squadEntity->getLogo()
                );
            }

            $this->flashMessenger()->addSuccessMessage('Squad successfully deleted!');
            $this->getEntityManager()->remove($squadEntity);
            $this->getEntityManager()->flush();
        }

        return $this->redirect()->toRoute('frontend/user/squads');
    }

    public function editAction()
    {
        $squadID = $this->params('id', 0);

        $squadRepo = $this->getEntityManager()->getRepository('Frontend\Squads\Entity\Squad');

        /** @var \Frontend\Squads\Entity\Squad $squadEntity */
        $squadEntity = $squadRepo->findOneBy(array(
            'user' => $this->identity(),
            'id' => $squadID
        ));

        if( ! $squadEntity )
        {
            $this->flashMessenger()->addErrorMessage('Squad not found');
            return $this->redirect()->toRoute('frontend/user/squads');
        }

        $squadEntityOriginal = clone $squadEntity;

        $form  = new SquadForm();
        $form->setEntityManager(
            $this->getEntityManager()
        );
        $form->init();
        $form->bind($squadEntity);

        if( $this->getRequest()->isPost() )
        {
            $form->setData(
                array_merge_recursive($_POST, $_FILES)
            );

            if( $form->isValid() )
            {
                /** @var \Frontend\Squads\Entity\Squad $squad */
                $squad = $form->getData();
                $squad->setUser(
                    $this->getEntityManager()->getReference('Auth\Entity\Benutzer', $this->identity()->getId() )
                );

                $squadImageService = $this->getServiceLocator()->get('SquadImageService');
                $uploadedLogoSpecs = $squad->getLogo();

                // delete logo
                if( $this->getRequest()->getPost('deleteLogo', 0) == 1 && $squadEntityOriginal->getLogo() )
                {
                    $squadImageService->deleteLogo(
                        $squadEntityOriginal->getLogo()
                    );
                    $squad->setLogo(null);
                } else {

                    // no logo change
                    $squad->setLogo(
                        $squadEntityOriginal->getLogo()
                    );
                }

                // set new logo?
                if( $uploadedLogoSpecs && $uploadedLogoSpecs['error'] != 4 )
                {
                    // delete old first
                    if($squadEntityOriginal->getLogo())
                    {
                        $squadImageService->deleteLogo(
                            $squadEntityOriginal->getLogo()
                        );
                    }

                    $squadLogoID = $squadImageService->saveLogo(
                        $uploadedLogoSpecs
                    );

                    if( $squadLogoID !== false )
                    {
                        $squad->setLogo($squadLogoID);
                    } else {
                        $squad->setLogo(null);
                    }
                }

                $this->getEntityManager()->merge( $squad );
                $this->getEntityManager()->flush();

                $this->flashMessenger()->addSuccessMessage('Squad successfully edited!');
                return $this->redirect()->refresh();
            }
            else
            {
                $form->populateValues($this->getRequest()->getPost());
            }
        }

        $viewModel = new ViewModel();
        $viewModel->setTemplate('/squads/edit.phtml');
        $viewModel->setVariable('form', $form);
        return $viewModel;
    }

    public function createAction()
    {
        $form  = new SquadForm();
        $form->setEntityManager(
            $this->getEntityManager()
        );
        $form->init();

        if( $this->getRequest()->isPost() )
        {
            $form->setData(
                array_merge_recursive($_POST, $_FILES)
            );

            if( $form->isValid() )
            {
                /** @var \Frontend\Squads\Entity\Squad $squad */
                $squad = $form->getData();
                $squad->setUser(
                    $this->getEntityManager()->getReference('Auth\Entity\Benutzer', $this->identity()->getId() )
                );

                $squadImageService = $this->getServiceLocator()->get('SquadImageService');
                $uploadedLogoSpecs = $squad->getLogo();

                // logo set?
                if( $uploadedLogoSpecs && $uploadedLogoSpecs['error'] != 4 )
                {
                    $squadLogoID = $squadImageService->saveLogo(
                        $uploadedLogoSpecs
                    );
                    if( $squadLogoID !== false )
                    {
                        $squad->setLogo($squadLogoID);
                    } else {
                        $squad->setLogo(null);
                    }
                } else {
                    // no logo change
                    $squad->setLogo(null);
                }

                // new squad url
                $squadRepo = $this->getEntityManager()->getRepository('Frontend\Squads\Entity\Squad');
                $squadRepo->createUniqueToken($squad);

                $this->getEntityManager()->persist( $squad );
                $this->getEntityManager()->flush();

                $this->flashMessenger()->addSuccessMessage('Squad successfully created!');
                return $this->redirect()->toRoute('frontend/user/squads');
            }
            else
            {
                $form->populateValues($this->getRequest()->getPost());
            }
        }


        $viewModel = new ViewModel();
        $viewModel->setTemplate('/squads/create.phtml');
        $viewModel->setVariable('form', $form);
        return $viewModel;
    }
}
