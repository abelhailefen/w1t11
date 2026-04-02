# Complete Delivery Guide Draft

## What this is

This guide is based on the approach that tends to work well when using a coding agent seriously.

It is not meant to be a rigid rulebook. Think of it more like: this is the path that usually works, these are the things worth paying attention to, these are the places where it helps to push the agent harder, and this is how the work stays from drifting into low-quality output.

The basic idea is simple: the agent does a lot of the implementation work, but the developer still drives the project. The developer sets the direction, protects the quality bar, decides what good looks like, and makes sure nothing weak slips through just because the agent sounded confident.

If you use an agent well, you get speed. If you use one lazily, you get fast garbage. This guide is about getting the first outcome.

## The job you are actually doing

It helps to think of this less as "asking AI to code" and more like working with a very fast engineer who still needs direction, review, and a quality bar.

In practice, that usually means doing a few things consistently:

- understand the prompt properly
- keep the work aligned with the prompt the whole way through
- decide what stage the project is in
- tell the agent what matters right now
- review the results instead of trusting the summary
- reject weak planning, weak implementation, weak tests, weak docs, and weak proof
- decide when something is actually done

If you do that well, the agent becomes much more useful. It plans better, codes better, verifies more honestly, and creates far less late-stage rework.

## Mindset/Approach

There are a few habits that matter more than almost anything else.

First, stay faithful to the original prompt. The agent will often try to reduce the problem into something easier to build. Sometimes it will do that openly, and sometimes it will do it without realizing it. So it helps to keep checking whether the project still matches the real goal, the real flows, and the real constraints.  
Second, be precise. Generic orders usually are not that helpful, so targeted reviews and targeted requests tend to work better.  
Third, do not accept completion based only on the agent's claim. The summary is useful, but the code, tests, docs, and runtime behavior are what count.  
Fourth, make the agent earn trust gradually. Early on, close review helps. As it proves it can carry work properly, the review can become more selective. But judgment still matters all the way through.

## What good agent direction sounds like

Good direction feels like a strong technical lead talking to a capable engineer. It is clear, specific, and grounded in the actual work.

Bad direction is vague. It sounds like "continue," "make it better," or "finish this." That kind of message leaves too much space for the agent to guess, and guesses are where scope drift and weak quality come from.

Better direction usually says four things:

1. what needs to be done now
2. what constraints matter
3. what must not be done
4. what proof you expect back

For example, instead of saying "finish auth," say something like this:

```text
Implement the authentication and tenant-isolation part of the project end to end.
The required user-facing and admin-facing surfaces need to work through their real paths, not just through direct API calls.
Use targeted local verification while you work.
Do not use Docker or the full suite for ordinary iteration.
When you report back, include the exact tests you ran, any local runtime checks, and any remaining release-facing risk.
```

That gives the agent a target, a boundary, and a standard.

## The development stages

One of the cleanest ways to keep control is to move through the project in distinct stages instead of blurring everything together. A lot of late-stage pain comes from pretending planning, implementation, verification, and tightening are all the same thing.

These are the stages that tend to work well:

1. `P0 Prompt Understanding and Clarification`
2. `P1 Planning`
3. `P2 Setup`
4. `P3 Development`
5. `P4 Tightening`
6. `P5 Review Findings`
7. `P6 Fix Pass`
8. `P7 Final Check Before Packaging`
9. `P8 Packaging`

Special tracking software usually is not necessary. A simple markdown file, shared note, or lightweight checklist is enough, as long as three things are always clear: what stage the project is in, what is blocked, and what needs to be true before moving forward.

## A lightweight project-state note

A lightweight project-state note helps a lot. It does not need to be fancy, and there is usually no reason to maintain it all by hand. In practice, one of the easiest ways to do this is to ask the coding agent to keep a short running state document updated for both of you.

At minimum, track:

- the original prompt
- the current stage
- the current coding-agent session
- what was accepted last
- what is still open
- what documents need updating
- whether you've already used any of your heavy verification runs

If you want a quick template, something like this is enough:

```md
# Delivery Tracker

## Prompt

- copied here or linked

## Current Stage

- P0 / P1 / P2 / ... / P8

## Current Session

- build / tightening-and-fixes

## Current Status

- in progress
- blocked
- accepted last

## Open Findings

- severity
- finding
- evidence
- next action

## Docs

- planning and delivery docs
- repo README.md

## Heavy Verification Runs

- run 1 used? yes/no
- run 2 used? yes/no
- run 3 used? yes/no
```

Again, the tool does not matter much. The discipline does. If manual tracking sounds annoying, letting the coding agent maintain this note usually works fine as long as it still gets sanity-checked as the project moves forward.

You can make that part of the process explicit.

For example:

```text
Create a short project-state note for this task and keep it updated as we go.
Keep it lightweight.
I only want: current stage, current focus, open issues, docs that still need updating, and whether we've used any heavy verification runs.
```

## How to use coding-agent sessions well

It is usually cleaner to think of the work in two coding-agent sessions rather than one enormous conversation.

The first session is the build session. This is where planning, setup, and most development happen.

The second session is the tightening and fixes session. This is for the later phase of the project, when the work has shifted from building to reviewing, tightening, and fixing issues you agree are real.

New sessions are usually worth creating only when the mode of work changes enough that a fresh context is actually helpful.

When you do switch sessions, leave yourself a proper handoff. Have the agent write down what was completed, what still looks risky, what was last verified, and what the next session should attack first. Then use that note to start the new session and get it oriented quickly.

You do not need to write that handoff yourself unless you want to. Just ask for it.

**Important**: Pay attention to the context window. If you're using a model with a very large context window, avoid letting a session climb much past 300k tokens because performance usually gets noticeably worse. Compact the session before it gets bloated. Many tools have a `/compact` command or something similar.
If you're using a smaller-window model, this usually takes care of itself because the tool will compact or roll over earlier.

```text
We're starting a fresh session for this project.
Write a detailed handoff note with: what is complete, what still looks risky, what was last verified, and what this next session should attack first.
Keep it practical and short.
```

You can write this to a file.

## When to run the heavy checks

One of the easiest ways to waste time with an agent is to let it run the heaviest possible verification loop, especially Docker, after every small change. That is usually quite inefficient.

It usually works better to keep expensive, project-wide checks rare across the whole project.
These are the moments where it tends to make sense.

1. setup, just once to verify the initial foundation works.
2. development complete, to verify the whole project holds together and fix what breaks
3. right before packaging, for one last clean confirmation run

Everything else should use the fastest meaningful check.

That usually means local runtime checks, targeted unit tests, targeted integration tests, checks for the part of the app you just changed, and local Playwright when the UI actually matters.

I like telling the agent this directly so it stays focused on local tests, and then I run the heavier checks myself when it's actually time for them.

## Things you should never wave through

There are a few categories of weak work that should almost always be rejected.

Don't accept fake tests. Don't accept weak evidence. Don't accept missing real surfaces. Don't accept release-facing breakage just because the happy path demo worked once. Don't accept placeholder or demo UI in real product screens. Don't accept `.env` files in the repo. Don't accept hardcoded secrets. Don't accept API shortcuts as substitutes for required user or admin flows. Don't accept vague claims that something was tested if the agent can't show what it ran and what happened.

Also, do not accept mocked APIs as integration evidence. If the project depends on real frontend/backend or service-to-service interaction, integration proof should use real HTTP requests against the actual running service surface.

Also, be very careful around security-sensitive areas. If the feature touches authentication, authorization, object ownership, multi-user or multi-tenant isolation, admin pages, debug routes, file access, or secrets, the quality bar should go up immediately.

## Staged execution

Everything above is the high-level view. The rest of the guide walks through each stage in more practical terms.

## Stage 0: Prompt understanding and clarification

This is a good way to start: first make sure the prompt is understood properly, then tighten it into something safe enough to build from.

Start by reading the prompt carefully and figuring out what kind of project this is likely to be. Notice any obvious stack constraints, environment restrictions, or missing assumptions that will matter later. If you're starting from scratch, prepare the project root and the basic folder structure you expect to need.

Then it usually helps to use another agent or model as a thinking partner before planning starts. This is a good place to use an external chat or a separate session just to unpack the request cleanly. The goal here is not architecture yet. The goal is to understand what is actually being asked.

Take the prompt apart. Identify the explicit requirements, the implied requirements, the user flows, the roles, the permissions, the entities, the edge cases, and any quality or verification expectations hiding inside it.

Write down the meaningful ambiguities in a `questions.md` document. For each one, capture what was unclear, how you currently understand it, and how it was resolved. If you need a safe default, choose the one that preserves the prompt best. Never choose the easier or narrower interpretation just because it sounds simpler to implement.

This phase should feel careful, not bureaucratic. You are not trying to ask a huge number of questions. You are trying to remove the few ambiguities that would otherwise blow up later.

Then write a clarified implementation brief that combines the original prompt with the decisions captured in `questions.md`.

Before moving on, compare the original prompt against the clarified version and ask: did this weaken anything, narrow anything, or silently reinterpret anything important? If the answer is yes, fix it before continuing.
If you want an extra check, compare the original prompt and the clarified brief in a separate chat and ask whether they still line up.

### How to direct the agent here

This works best when you ask the agent to pull the prompt apart first and interpret it second.

Example prompt:

```text
Read this prompt carefully and turn it into a clear implementation brief.
Pull out the explicit requirements, implied constraints, user-facing flows, admin or operator flows if any, and the ambiguities that actually matter.
Where something is ambiguous, suggest the safest default that preserves the full intent instead of narrowing the scope.
Do not plan the architecture yet.
```

If the result is still vague, push harder:

```text
This is still too loose.
I need the real requirements and real flows pulled out of the prompt, not a summary.
Be specific about what must exist, what users can do, what admins can do, and what would count as drift from the original request.
```

## Stage 1: Planning

Planning decides a lot of how painful the rest of the project will be. If planning is strong, later stages are cleaner. If planning is weak, the later review and fix pass turn into expensive damage control.

At this stage, the goal is not a vague architecture sketch. The goal is a plan detailed enough that a coding agent can build from it without constantly inventing missing structure on the fly.

That plan should live in a real document. In many projects, `design.md` is the right place for it. The exact filename matters less than the fact that there is one written source of truth the agent can keep working from instead of reinventing the plan in chat over and over.

The most important thing to do here is to plan against the four things that usually decide whether the project holds up later:

1. does it still match the original request?
2. are the security boundaries solid?
3. are the tests going to be strong enough?
4. will the codebase still feel sane once the real build starts?

In other words, don't wait until the end to ask whether the system still matches the prompt, whether auth is coherent, whether the tests are strong enough, or whether the architecture is maintainable. Make those planning concerns from the start.

The plan should cover architecture, module boundaries, data model, contracts, frontend/backend crosswalks, shared state and lifecycle models, authentication and permission structure, operator or admin surfaces, logging and observability, runtime expectations, docs implications, and major risks.

More importantly, it should cover the whole build in enough detail that the agent is not inventing the project one corner at a time. That means planning all major modules, how they connect, what each one is responsible for, which surfaces they power, which contracts they depend on, and how each one will be verified.

If the project is fullstack, the plan should make it easy to answer questions like these for every meaningful part of the product:

- which page, route, screen, or component group is being built
- which backend module, endpoint, job, or service supports it
- what data shape or contract connects them
- what permissions or roles apply
- what failure cases matter
- what tests prove it works

The planning conversation should also force concrete thinking. If the system is configurable, plan the real configuration surface. If the prompt implies admin management, plan the actual admin flow. If the project is fullstack, map UI surfaces to backend modules and data shapes explicitly. If there are meaningful security or data-governance concerns, define what “done” means across all of them instead of leaving that vague.

This is also the place to define the cross-cutting rules that should stay consistent everywhere instead of being reinvented feature by feature. That usually includes error handling, user-visible validation and feedback, permission enforcement across UI and API layers, logging and redaction rules, auth edge cases like expiry or refresh, and any state-transition or context-switch behavior that affects more than one part of the app.

Testing should be planned in depth here, not waved at with a line about "good coverage." The plan should say what gets unit tests, what gets integration tests, what needs end-to-end coverage, which happy paths matter most, which failure paths matter most, which security-sensitive behaviors need direct proof, and which cross-module seams are risky enough to test on purpose. If the UI matters, the plan should also say where screenshots or browser-driven checks are expected.

By the end of planning, you should be able to point to a full test story for the project, not just isolated test ideas.

The plan should also leave behind honest planning docs. The design document should describe the structure, module map, phase plan, and decisions clearly enough that later implementation still matches it. The API spec should describe the real interfaces, data shapes, request and response expectations, auth rules, and error behavior for the parts of the system that expose an API. The test-coverage document should explain what the plan expects to be proven and at what level.

Those docs should agree with each other. If the design document, API spec, and test-coverage document tell different stories about the same feature, the planning work is not really done.

Once that plan document exists, the easiest way to keep the build on track is to have the agent work from it directly. Don't just say "keep going." Tell the agent which phase it is in, which part of the written plan it is implementing, and what proof you expect back for that phase.

A strong way to review the plan is to ask four simple questions:

- Does this still match the real business goal?
- Are the security boundaries clear enough to build safely?
- Is the test strategy strong enough to catch the major failures later?
- Is this architecture likely to stay sane once real implementation starts?

If you notice a concrete scope mismatch, contract mismatch, or role mismatch, don't accept the plan until it is corrected or explicitly explained.

You can leave planning once the plan is concrete enough to drive real implementation and those four questions have been answered explicitly.

This is where the main coding-agent session starts.

### How to direct the agent here

1. start the main coding session
2. send the original prompt first with a message like `Let's plan this project: <original prompt>`
3. let the agent respond once from the raw prompt
4. then send the clarified version with the important assumptions, constraints, and interpretations written down clearly
5. continue the planning phase from there in that same main session

That sequence matters because it gives the agent both:

- the original project request in its raw form
- the refined clarified brief that removes ambiguity and pins down the right direction

In practice, that means you usually use one place or one agent session to clarify the task, and then start the real build session with the planning flow above.

Example flow:

```text
Let's plan this project: <original prompt>
```

Then after the first response:

```text
Use this clarified implementation brief as the source of truth while planning:

<clarified prompt with the important specifications, assumptions, and constraints written down clearly>

Now continue the planning phase from that basis and turn it into a plan we can actually build from.
```

Example prompt:

```text
Create a plan for this project that we can actually build from.
I want real module boundaries, data and contract thinking, frontend/backend mapping where relevant, auth and permission considerations, test strategy, and the major risks.
Plan every major module and feature area, how they connect, what contracts they use, and how each one will be tested.
I do not want gaps where the agent can invent missing structure later.
Write this plan into `design.md` and structure it so we can build from it phase by phase.
Plan explicitly for request match, serious security risks, test strength, and overall code quality so we do not leave those for the end.
Do not give me a vague architecture sketch.
```

If the plan sounds clever but still too shallow:

```text
This still reads like a high-level outline.
Tighten it.
I need a plan that is concrete enough to build from without inventing major structure later.
Be explicit about every major module, real user flows, admin flows, contracts, tests, and security-sensitive areas.
I want to see the development map and the testing map, not just architecture words.
Update `design.md` so the written plan is detailed enough to drive the build.
```

## Stage 2: Setup

Setup is where the foundation gets built properly.

This is not the phase for fake progress. If the setup is weak, the rest of the project will keep tripping over it.

What matters here is simple:

- a real project structure
- real runtime wiring
- a local way to run and verify the project
- the basic config, logging, and setup the app actually needs
- any baseline security or infrastructure that clearly belongs in the foundation
- an honest README skeleton

The easiest way to think about it is this: after setup, later work should be building on something real, not fixing the basics.

A weak setup often looks fine at first glance. It has folders, config files, and maybe something that boots. But once you inspect it, the important behavior is still placeholder-level. That's what you're trying to avoid.

This is usually the first place to spend one heavy verification run.

You can leave setup once the foundation is real enough that later modules and features will not have to retrofit the basics.

### How to direct the agent here

Setup prompts should be practical and grounded.

At the start of each stage, tell the agent that stage explicitly and point it back to the written plan. That keeps the conversation anchored to something stable.

Example prompt:

```text
We're in the setup phase now.
Use `design.md` as the source of truth for this phase.
Set up the real project foundation for this plan.
I want real runtime wiring, a usable local verification path, the basic config and logging the app needs, and an honest README skeleton.
If there are obvious security or infrastructure foundations that belong here, make them real now.
Do not create placeholder structure that only looks finished.
Keep the planning and delivery docs aligned with what gets built, and keep `README.md` accurate inside the repo.
```

If the setup feels fake:

```text
This still looks decorative.
I need the foundations that later work can safely build on: real runtime behavior, real verification paths, and the baseline protections this project actually needs.
Tighten it and verify it locally.
```

## Stage 3: Development

This is usually the longest stage, and the one where your day-to-day direction matters most.

The work should happen in clearly scoped modules or feature chunks. Each one should carry its own local planning, implementation, tests, local verification, and doc sync. A good unit of work delivers a real chunk of product value through its intended surfaces and fits cleanly into the architecture.

This is where many projects go wrong. The coding agent may build only the backend and claim the feature is done. It may build only a UI shell with fake behavior and claim the flow exists. It may cover only happy paths. It may ignore failure handling, cross-cutting consistency, integration seams, or release-facing build issues.

Your review needs to catch that.

For each module or feature chunk, look at prompt alignment, surface completeness, security boundaries when relevant, path safety when files are involved, sanitized errors, integration seams, cross-cutting consistency, absence of prototype residue, and the actual quality of the verification evidence.

During this stage, ordinary verification should stay local and targeted. Use unit tests, integration tests, local runtime checks, and local Playwright for affected flows. If the frontend or tooling changed in a way that could hurt the release path, also check build health where it matters.

When you ask for integration proof, be explicit that mocked APIs are not enough. The point is to prove the real running surface, not a simulated one.

Avoid the temptation to rerun the biggest possible verification step after every chunk of work. That's expensive and usually unnecessary.

You can leave development once the planned modules and feature chunks are materially complete and the local proof is strong enough to justify a real whole-project check.

### How to direct the agent here

This is where the agent needs the clearest and most grounded direction.

When assigning a module or feature chunk, be specific about the real surface and the real proof you expect.

Example prompt:

```text
We're in the development phase now.
Use `design.md` as the source of truth and implement the next planned module or feature chunk.
Implement the next complete module or feature chunk end to end.
Make the real user-facing and admin-facing surfaces work where they are part of the requirement.
Do the local verification as you go, and when you report back, include the exact tests or runtime checks you ran.
Do not use API shortcuts to stand in for required product surfaces.
Update the design, API, and test docs during the work so they stay in sync with the code, and keep `README.md` accurate inside the repo.
```

If the work comes back incomplete:

```text
This is not complete yet.
The backend exists, but the real surface is still missing and the verification is too weak.
Finish the actual flow, tighten the checks, and come back with proof that covers the changed area properly.
```

If the verification is weak:

```text
The implementation may be fine, but the proof is not strong enough.
Rerun this with targeted local verification for the changed area and show me exactly what passed.
If the UI changed, include Playwright evidence and screenshots where appropriate.
```

If the feature depends on real integration behavior, say so directly:

```text
Do not use mocked APIs for this verification.
I want proof against the real running service surface with real HTTP requests where integration behavior matters.
```

By the end of development, you should stop asking whether each individual part works on its own and start asking whether the whole thing holds together. This is where breaks between parts of the app, route-level failures, shared-helper issues, UI/backend mismatches, and hidden release-facing problems usually show up. Run that whole-project check before you move into tightening, and once failures are known, stop blindly rerunning everything. Figure out what failed, isolate the affected surface, fix it, and prove the fix narrowly first. Only go broad again when the narrower checks are no longer answering the real question.

This is usually the second heavy verification run.

## Stage 4: Tightening

This is the stage where the project gets made ready to hold up under serious review.

This stage should explicitly review the project through the same four questions again:

1. does it still match the original request?
2. are there any serious security problems left?
3. are the tests strong enough to trust?
4. does the codebase still look solid and maintainable?

This is not just a cleanup stage. It is where you deliberately ask: does the delivered project still match the business goal, or did it drift? Are there unresolved auth or isolation issues? Are the tests actually strong enough to rule out most major issues? Does the code still look like a credible, maintainable system, or like something piled together too fast?

You should also use this stage for secret and config hygiene, prototype residue cleanup, logging and observability checks, redaction checks, docs honesty, release-candidate cleanup, and exploratory testing around awkward states and repeated actions.

If this stage uncovers real instability that belongs to unfinished development work, go back and fix it. Don't pretend the project is merely in cleanup when it is actually still unstable.

You can leave this stage only when you have clear answers to those four questions and the answers are good enough to justify the final review pass.

### How to direct the agent here

Prompts here should feel like a serious pre-release review.

Example prompt:

```text
We're in the tightening phase now.
Use `design.md` and the rest of the current docs as the source of truth while you review the project.
Review and tighten the project against these four areas: does it still match the request, are there serious security problems, are the tests strong enough, and does the codebase still look solid.
Focus on real weaknesses, not cosmetic polish.
If you find instability that belongs to unfinished development work, call it out directly instead of pretending this is just cleanup.
Update the docs where the review reveals drift, and fix `README.md` too if the repo-facing usage docs changed.
```

If you want to push more specifically on security and tests:

```text
I want a tightening pass focused on security and test strength.
Look for auth and authorization issues, ownership or isolation gaps, weak negative-path coverage, weak docs, and anything that would likely show up in a serious review.
Fix what is genuinely fixable now and tell me clearly what still looks risky.
```

## Stage 5: Review findings

This is where the final review pass happens and where it becomes clear which findings actually matter.

Use fresh review sessions. Don't reuse the development conversation. Give the reviewer the full prompt, the full project, and a clean perspective.

If you have both frontend and backend, review them separately. Keep large reports saved to files if they are long.

Then sort the results. Not every finding deserves the same weight. Some things absolutely need a fix pass. Some things are real but not serious enough to block delivery. Some findings will be weak or overreaching. Your job is to judge them, not worship them.

In general, `Blocker` and `High` findings should go to the fix pass. `Medium` findings usually should be fixed if they materially affect confidence or correctness. `Low` findings can often pass.

If a report says something could not be verified, do not automatically panic. Ask whether your own direct evidence already answers the question. If it does, defend the project. If it does not, decide whether that gap is important enough to fix.

You can leave this stage once you know whether a fix pass is required.

### How to direct the agent here

At this point the agent is helping you think, not just build.

Example prompt:

```text
Read these review findings and help me sort them.
Separate what is genuinely blocking from what is medium-value or weak.
If a finding is overstated or contradicted by stronger direct evidence, say so clearly.
If fixes are needed, give me a tight fix list instead of a vague rewrite plan.
```

## Stage 6: Fix pass

If the review produced findings you agree are real, this is where you fix them.

Keep this pass tight. Do not let it turn back into broad feature development. Fix the findings you agree are real, rerun the relevant proof, update docs if behavior changed, and keep the evidence reproducible.

If a finding exposed docs drift or an acceptance gap, fix that too. Don't just patch the symptom and leave the surrounding weakness untouched.

You can leave this stage when the findings you accepted are fixed and reverified. If the fix pass reveals broader instability in the build, go back and fix that properly before you call it done.

### How to direct the agent here

This pass should be tight and focused.

Example prompt:

```text
Fix only these findings.
Do not do broad feature work.
For each one, make the fix cleanly, rerun the relevant proof, and tell me exactly what changed.
If a fix changes behavior or docs, update those too.
```

## Stage 7: Final check before packaging

This stage is short, but it is a real gate. At this point, there is only one decision left: package now, or go back and fix more.

Do not wave this through because the project feels close. Check that the findings you accepted have really been fixed, that the docs match reality, that the repo is in a clean state, and that packaging can begin without last-minute guesswork.

If anything still feels uncertain, stop here and correct it before packaging starts.

## Stage 8: Packaging

Packaging is the final assembly step, and it is not optional or flexible. This is the most critical stage because it decides what actually gets delivered.

Packaging is manual. You can use the coding agent to check structure, call out missing items, or compare the package against the rules but also verify manually.

At this stage, the package structure must be correct, the required files must exist, the project must run, and the final deliverable must be complete. Close enough is not acceptable here.

There are two different things to manage here:

- the final package itself
- the submission-support documents and proof materials generated outside the package and used alongside submission

Do not blur those together. The submission-support documents are important, but they are not part of the final package structure.

By this point, you should already have:

- current docs
- an honest README
- review reports
- exported session files and converted history files
- a clean repo

The final package must include the delivered codebase under `repo/`, the docs set under `docs/`, the session artifacts, metadata, and the required export files.

Use this exact structure:

```text
package-root/
  docs/
    design.md
    test-coverage.md
    questions.md
    api-spec.md                  # when applicable
  repo/
    README.md
    <delivered codebase>
  sessions/
    <additional session artifacts if needed>
  metadata.json
  session.json                   # or session-N.json
  trajectory.json                # or trajectory-N.json
  .tmp/                          # only when preserved from packaging inputs
```

Required package files and directories:

- `docs/`
- `docs/design.md`
- `docs/test-coverage.md`
- `docs/questions.md`
- `docs/api-spec.md` when the project exposes an API
- `repo/`
- `repo/README.md`
- the delivered codebase inside `repo/`
- `sessions/trajectory.json` or `trajectory-N.json`
- `metadata.json`
- `session.json` or `session-N.json`

**Submission-support documents and proof materials:**

- `backend-evaluation-prompt.md`: backend or non-frontend evaluation runs
- `frontend-evaluation-prompt.md`: frontend evaluation runs
  These are critical to the form submission.

**For the forms**
These are generated outside the final package and used for the submission forms. They are not part of the package structure above.

- `self-test-results-hard-threshold.md`
- `self-test-status-delivery-completeness.md`
- `self-assessment-engineering-and-architecture-quality.md`
- `self-test-results-engineering-details-and-professionalism.md`
- `self-test-results-prompt-understanding-and-adaptability.md`
- `self-test-results-aesthetics.md` for UI-bearing projects, or `Not Applicable` when it does not apply
- repo structure screenshot
- working app screenshots
- any other proof reviewers need to inspect the delivered behavior

These documents are generated based on the following template/reference documents.

- `document-completeness-template.md`: is the template for `self-test-results-hard-threshold.md` and `self-test-status-delivery-completeness.md`
- `quality-document-template.md`: is the template for `self-assessment-engineering-and-architecture-quality.md`
- `engineering-results-template.md`: is the template for `self-test-results-engineering-details-and-professionalism.md`
- `implementation-comparison-template.md`: is the template for `self-test-results-prompt-understanding-and-adaptability.md`
- `self-test-results-aesthetics.md` should be based on the real delivered UI, screenshots, and shipped product state; the other packaging documents can support that judgment, but they should not replace actual UI evidence

Other packaging inputs that still matter:

- `metadata.json`, including the original project prompt stored there
- the final screenshots and proof artifacts

Treat the required package structure and required package file set as fixed. If something is supposed to be present, it must be present. If something is supposed to be in a specific place, it must be in that place. If the project is supposed to start and run from the delivered package, prove that it does.

Packaging rules:

- execute packaging in order instead of jumping to the end
- verify outputs after each major packaging block before continuing
- packaging is still in progress if any required directory or file is missing
- packaging is still in progress if any export, move, cleanup, or reporting step is incomplete
- `metadata.json` must be complete and must describe the delivered project truthfully
- keep `repo/README.md` honest and self-sufficient for running and testing the delivered codebase
- do not let `repo/README.md` depend on the docs outside `repo/`
- include the project's real primary full-test command and any required wrapper script in the delivered repo
- if `repo/docs/` exists, treat it as residue: reconcile anything missing into `docs/` and remove it from `repo/`
- generate the submission-support documents and proof materials outside the final package and keep them organized for submission
- after packaging, the final package should stand on its own, and the proof materials should be easy to review alongside it
- remove junk recursively from `repo/`, including `.git/`, tool state folders, editor folders, caches, `node_modules/`, build leftovers not part of delivery, env-file variants, and other local-only noise
- do not delete required evidence while cleaning up
- if packaging reveals a real defect or a missing artifact, packaging is not complete until that problem is fixed

If you are standing inside `repo/` during packaging, this is the export sequence for an OpenCode session:

1. `opencode export <session-id> > ../session.json`
2. `python3 ~/utils/convert_ai_session.py -i ../session.json -o ../trajectory.json`

Be strict here. Packaging is not cleanup theater. Do not declare it done early. Verify that the required package structure exists, that the required package files exist, that the docs are present, that the exports exist, that the repo is actually clean, and that the delivered project still starts and runs successfully. Separately verify that the submission-support documents and proof materials exist and are ready to be used alongside submission.

This is usually the third heavy verification run if you still need one final high-confidence check before packaging.

## Documentation you should keep updated

There are a few documents that matter a lot in this process. The planning and delivery docs should live outside the repo folder. The main exception is `README.md`, which should stay inside the repo. Repo files should stand on their own and should not point readers to those docs, but the docs themselves should stay aligned with the repo as it changes.

The questions document should reflect the real clarification decisions, not a fuzzy summary.

The design document should describe the actual delivered design and architecture, not the original plan after the project has drifted. It should start as the planning document and stay current as the work moves through setup, development, tightening, and fixes.

The API spec should do more than list endpoints. When the project exposes an API, it should spell out the real interfaces, payload shapes, auth rules, permission expectations, important error cases, and any contract details the frontend, integrations, or other services rely on.

The test-coverage document should explain what is actually covered and what isn't, honestly.

Do not leave documentation updates until the end. Keep the planning and delivery docs in accordance with the repo throughout development, and keep the repo in accordance with those docs.

`README.md` should explain what the project is, what it does, how to run it, and how to test it. It should be friendly to a junior developer and should stand on its own.

## Common mistakes

There are a few failure modes that come up again and again.

One is starting the coding agent too early, before clarification is really solid.

Another is accepting weak planning because it "looks detailed enough." Weak planning is expensive later.

Another is accepting weak proof because the coding agent sounded confident.

Another is burning your heavy verification runs too early and then having no patience left for the places they actually matter.

Another is letting the coding agent quietly narrow the scope.

Another is using the final review as the first serious check, instead of getting the project into good shape before that point.

And one more: doing too much of the coding agent's job yourself instead of using better direction, stronger review, and firmer acceptance discipline.

## A simple exit checklist for every stage

Whenever you think you're ready to move on, stop and ask the obvious questions.

Before leaving clarification, ask whether the prompt is really understood, whether the ambiguities are documented, whether the defaults are safe, whether drift was checked, and whether clarification was actually approved.

Before leaving planning, ask whether the plan is detailed enough to build from and whether it explicitly covered request match, serious security risks, test strength, and engineering quality.

Before leaving setup, ask whether the runtime foundation is real, whether local verification is real, whether baseline security and runtime behavior are real where required, and whether later modules and features will be building on something solid.

Before leaving development, ask whether the planned modules and feature chunks are really complete, whether the required surfaces exist, whether you've done a serious whole-project check, and whether failures are truly resolved rather than merely hidden behind narrow checks.

Before leaving tightening, ask whether you can answer the four key review questions clearly and whether the project would genuinely hold up under serious review.

Before leaving the fix pass, ask whether the findings you accepted were actually fixed, whether the right proof was rerun, and whether docs were updated if behavior changed.

Before leaving the final check before packaging, ask whether there is any real reason not to package yet, whether the accepted fixes truly closed the known gaps, whether the docs and repo agree, and whether packaging can begin without any missing decisions.

Before leaving packaging, ask whether the package structure is exactly correct, whether every required package file exists, whether `metadata.json` is present and truthful, whether the docs exist, whether the submission-support documents and proof materials exist outside the package, whether the session export and converted history file exist, whether the repo is clean, and whether the delivered project still starts and runs successfully.
