export type StepKey = "intro" | "details" | "review" | "success";

const DEFAULT_STEPS: StepKey[] = ["intro", "details", "review", "success"];

export function getStepSequence(customSteps?: StepKey[]): StepKey[] {
  if (Array.isArray(customSteps) && customSteps.length > 0) {
    return customSteps;
  }
  return DEFAULT_STEPS;
}

export function getNextStep(current: StepKey, steps: StepKey[] = DEFAULT_STEPS): StepKey {
  const index = steps.indexOf(current);
  const next =
    index >= 0 && index < steps.length - 1
      ? steps[index + 1]
      : steps[steps.length - 1];
  return next;
}


