<?php
/*
 * This file is part of the Sonata project.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\MediaBundle\Provider;

use Sonata\MediaBundle\Model\MediaInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileProvider extends BaseProvider
{
    /**
     * @param \Sonata\MediaBundle\Model\MediaInterface $media
     * @return string
     */
    public function getReferenceImage(MediaInterface $media)
    {
        return sprintf('%s/%s',
            $this->generatePath($media),
            $media->getProviderReference()
        );
    }

    /**
     * @param \Sonata\MediaBundle\Model\MediaInterface $media
     * @return \Gaufrette\File
     */
    public function getReferenceFile(MediaInterface $media)
    {
        return $this->getFilesystem()->get($this->getReferenceImage($media), true);
    }

    /**
     * Build the related create form
     *
     * @param \Sonata\AdminBundle\Form\FormMapper $formMapper
     */
    public function buildEditForm(FormMapper $formMapper)
    {
        $formMapper->add('name', null, array('required' => false));
        $formMapper->add('enabled', null, array('required' => false));
        $formMapper->add('authorName', null, array('required' => false));
        $formMapper->add('cdnIsFlushable', null, array('required' => false));
        $formMapper->add('description', null, array('required' => false));
        $formMapper->add('copyright', null, array('required' => false));
        $formMapper->add('binaryContent', 'file', array('required' => false));
    }

    /**
     * build the related create form
     *
     * @param \Sonata\AdminBundle\Form\FormMapper $formMapper
     */
    public function buildCreateForm(FormMapper $formMapper)
    {
        $formMapper->add('binaryContent', 'file');
    }

    /**
     * @param \Sonata\MediaBundle\Model\MediaInterface $media
     * @return
     */
    public function postPersist(MediaInterface $media)
    {
        if ($media->getBinaryContent() === null) {
            return;
        }

        $this->setFileContents($media);

        $this->generateThumbnails($media);
    }

    /**
     * @param \Sonata\MediaBundle\Model\MediaInterface $media
     * @return
     */
    public function postUpdate(MediaInterface $media)
    {
        if (!$media->getBinaryContent() instanceof \SplFileInfo) {
            return;
        }

        $this->fixBinaryContent($media);

        $this->setFileContents($media);

        $this->generateThumbnails($media);
    }

    /**
     * @throws \RuntimeException
     * @param \Sonata\MediaBundle\Model\MediaInterface $media
     * @return
     */
    public function fixBinaryContent(MediaInterface $media)
    {
        if ($media->getBinaryContent() === null) {
            return;
        }

        // if the binary content is a filename => convert to a valid File
        if (!$media->getBinaryContent() instanceof File) {
            if (!is_file($media->getBinaryContent())) {
                throw new \RuntimeException('The file does not exist : ' . $media->getBinaryContent());
            }

            $binaryContent = new File($media->getBinaryContent());

            $media->setBinaryContent($binaryContent);
        }
    }

    /**
     * @throws \RuntimeException
     * @param \Sonata\MediaBundle\Model\MediaInterface $media
     * @return void
     */
    protected function fixFilename(MediaInterface $media)
    {
        if ($media->getBinaryContent() instanceof UploadedFile) {
            $media->setName($media->getBinaryContent()->getClientOriginalName());
        } else if ($media->getBinaryContent() instanceof File) {
            $media->setName($media->getBinaryContent()->getBasename());
        } else {
            $mediaName = false;
        }

        // this is the original name
        if (!$media->getName() && !$mediaName) {
            throw new \RuntimeException('Please define a valid media\'s name');
        } else if (!$media->getName()) {
            $media->setName($mediaName);
        }
    }

    /**
     * @param \Sonata\MediaBundle\Model\MediaInterface $media
     * @return
     */
    public function prePersist(MediaInterface $media)
    {
        $this->fixBinaryContent($media);

        $media->setProviderName($this->name);
        $media->setProviderStatus(MediaInterface::STATUS_OK);

        if (!$media->getBinaryContent() instanceof \SplFileInfo) {
            return;
        }

        $this->fixFilename($media);

        // this is the name used to store the file
        if (!$media->getProviderReference()) {
            $media->setProviderReference(sha1($media->getName() . rand(11111, 99999)) . $media->getBinaryContent()->getExtension());
        }

        $media->setContentType($media->getBinaryContent()->getMimeType());
        $media->setSize($media->getBinaryContent()->getSize());
        $media->setCreatedAt(new \Datetime());
        $media->setUpdatedAt(new \Datetime());
    }

    /**
     * @param \Sonata\MediaBundle\Model\MediaInterface $media
     * @param string $format
     * @return string
     */
    public function generatePublicUrl(MediaInterface $media, $format)
    {
        // todo: add a valid icon set
        return $this->getCdn()->getPath(sprintf('media_bundle/images/files/%s/file.png', $format), $media->getCdnIsFlushable());
    }

    /**
     * @param \Sonata\MediaBundle\Model\MediaInterface $media
     * @param string $format
     * @param array $options
     * @return array
     */
    public function getHelperProperties(MediaInterface $media, $format, $options = array())
    {
        return array_merge(array(
          'title'       => $media->getName(),
          'thumbnail'   => $this->getReferenceImage($media),
          'file'        => $this->getReferenceImage($media),
        ), $options);
    }

    /**
     * @param \Sonata\MediaBundle\Model\MediaInterface $media
     * @param string $format
     * @return bool
     */
    public function generatePrivateUrl(MediaInterface $media, $format)
    {
        return false;
    }

    /**
     * @param \Sonata\MediaBundle\Model\MediaInterface $media
     * @return
     */
    public function preUpdate(MediaInterface $media)
    {
        $this->fixBinaryContent($media);

        if (!$media->getBinaryContent() instanceof \SplFileInfo) {
            return;
        }

        $this->fixFilename($media);

        // this is the name used to store the file
        if (!$media->getProviderReference()) {
           $media->setProviderReference(sha1($media->getName() . rand(11111, 99999)) . $media->getBinaryContent()->getExtension());
        }

        $media->setContentType($media->getBinaryContent()->getMimeType());
        $media->setSize($media->getBinaryContent()->getSize());
        $media->setUpdatedAt(new \Datetime());
    }

    /**
     * @param \Sonata\MediaBundle\Model\MediaInterface $media
     * @return void
     */
    public function preRemove(MediaInterface $media)
    {

    }

    /**
     * Set the file contents for an image
     *
     * @param \Sonata\MediaBundle\Model\MediaInterface $media
     * @param $contents path to contents, defaults to MediaInterface BinaryContent
     * @return void
     */
    public function setFileContents(MediaInterface $media, $contents=null)
    {
        $file = $this->getFilesystem()->get(
            sprintf('%s/%s', $this->generatePath($media), $media->getProviderReference()),
            true
        );
        if (!$contents) {
            $contents = $media->getBinaryContent()->getRealPath();
        }
        $file->setContent(file_get_contents($contents));
    }
}