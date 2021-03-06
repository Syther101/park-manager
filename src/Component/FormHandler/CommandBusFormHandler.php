<?php

declare(strict_types=1);

/*
 * This file is part of the Park-Manager project.
 *
 * Copyright (c) the Contributors as noted in the AUTHORS file.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace ParkManager\Component\FormHandler;

use ParkManager\Component\ApplicationFoundation\Command\CommandBus;
use Symfony\Component\Form\Exception\AlreadySubmittedException;
use Symfony\Component\Form\Exception\BadMethodCallException;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Throwable;
use function explode;
use function get_class;
use function is_array;

final class CommandBusFormHandler implements FormHandler
{
    private $form;
    private $commandBus;
    private $validator;

    private $handled = false;
    private $ready   = false;

    /** @var callable[] */
    private $exceptionFormatters = [];

    /** @var callable|null */
    private $fallbackFormatter;

    public function __construct(FormInterface $form, CommandBus $commandBus, ?callable $validator = null)
    {
        $this->form       = $form;
        $this->commandBus = $commandBus;
        $this->validator  = $validator;
    }

    public function mapException(string $exceptionClass, callable $formatter): void
    {
        if ($this->handled) {
            throw new AlreadySubmittedException('Cannot configure handler once Form is Handled.');
        }

        $this->exceptionFormatters[$exceptionClass] = $formatter;
    }

    public function setExceptionFallback(callable $formatter): void
    {
        if ($this->handled) {
            throw new AlreadySubmittedException('Cannot configure handler once Form is Handled.');
        }

        $this->fallbackFormatter = $formatter;
    }

    public function getForm(): FormInterface
    {
        return $this->form;
    }

    public function handleRequest($request, bool $autoExecute = true)
    {
        if ($this->handled) {
            throw new AlreadySubmittedException('A form can only be handled once.');
        }

        $this->form->handleRequest($request);
        $this->handled = true;

        if ($this->validator !== null && ! $this->form->isSubmitted()) {
            ($this->validator)($this->form->getData());
        }

        if ($this->form->isSubmitted() && $this->form->isValid()) {
            return $this->dispatch();
        }
    }

    public function createView(): FormView
    {
        return $this->form->createView();
    }

    private function dispatch()
    {
        try {
            $result = $this->commandBus->handle($this->form->getData());

            $this->ready = true;

            return $result;
        } catch (Throwable $e) {
            $exceptionName = get_class($e);

            if (isset($this->exceptionFormatters[$exceptionName])) {
                $errors = $this->exceptionFormatters[$exceptionName]($e);
            } elseif ($this->fallbackFormatter !== null) {
                $errors = ($this->fallbackFormatter)($e);
            } else {
                throw $e;
            }

            $this->mapErrors($errors);
        }
    }

    public function isReady(): bool
    {
        if (! $this->handled) {
            throw new BadMethodCallException('handleRequest() must to be called before a Forms readiness can be checked.');
        }

        return $this->ready;
    }

    private function mapErrors($errors): void
    {
        if (! is_array($errors)) {
            $errors = [null => [$errors]];
        }

        foreach ($errors as $formPath => $formErrors) {
            if (! is_array($formErrors)) {
                $formErrors = [$formErrors];
            }

            $formPath = (string) $formPath;
            $form     = $this->form;

            if ($formPath !== '') {
                foreach (explode('.', $formPath) as $child) {
                    $form = $form->get($child);
                }
            }

            foreach ($formErrors as $error) {
                $form->addError($error);
            }
        }
    }
}
