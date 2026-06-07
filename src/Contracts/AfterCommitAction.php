<?php

namespace Maestrodimateo\Workflow\Contracts;

/**
 * Marker interface for transition actions whose `execute()` must run AFTER
 * the surrounding DB transaction has been committed.
 *
 * Use it on actions that produce non-rollbackable side effects (sending
 * emails, calling webhooks, writing logs, dispatching external API calls).
 * Without this marker, an action runs inside the transition's transaction,
 * so a later failure rolls back the DB state — but a side effect already
 * emitted (mail sent, HTTP request fired) cannot be undone.
 *
 * Actions that only validate state or write to the DB should NOT implement
 * this interface: they belong inside the transaction so a thrown exception
 * properly rolls back the transition.
 */
interface AfterCommitAction {}