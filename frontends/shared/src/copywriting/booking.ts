export type BookingStepKey = "intro" | "details" | "review" | "success";

const STEP_ORDER: BookingStepKey[] = ["intro", "details", "review", "success"];
const STEP_LABELS: Record<BookingStepKey, string> = {
  intro: "Étape 1",
  details: "Étape 2",
  review: "Étape 3",
  success: "Terminé",
};

export const BOOKING_COPY = {
  CTA_START: "Commencer la réservation",
  CTA_CONTINUE: "Continuer",
  CTA_CONFIRM: "Confirmer la réservation",
  SUCCESS_DEFAULT_HEADING: "Merci, ta demande est enregistrée !",
  SUCCESS_SECONDARY_HEADING:
    "Notre équipe revient vers toi sous 24h pour valider la session.",
  CTA_NEW_REQUEST: "Nouvelle demande",
  SUCCESS_PARAGLIDING_HEADING: "Merci, ton vol est enregistré !",
};

export function getBookingStepLabel(step: BookingStepKey): string {
  return STEP_LABELS[step] ?? STEP_LABELS.intro;
}

export function getStepIndex(step: BookingStepKey): number {
  const idx = STEP_ORDER.indexOf(step);
  return idx === -1 ? 0 : idx;
}


