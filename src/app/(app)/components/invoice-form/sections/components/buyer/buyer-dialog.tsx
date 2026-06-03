import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { toSchema, type ToData } from "@/app/schema";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";
import { Switch } from "@/components/ui/switch";
import { CustomTooltip } from "@/components/ui/tooltip";
import { toast } from "sonner";
import { ConfirmDiscardDialog } from "../confirm-discard-dialog";
import { TOS_LOCAL_STORAGE_KEY } from "./buyer-management";
import { useState, useEffect } from "react";
import * as Sentry from "@sentry/nextjs";
import { InputHelperMessage } from "../../../../../../../components/ui/input-helper-message";
import { useConfirmDiscard } from "@/app/(app)/components/invoice-form/sections/hooks/use-confirm-discard";

const TO_FORM_ID = "to-form";

interface ToDialogProps {
  isOpen: boolean;
  onClose: React.Dispatch<React.SetStateAction<boolean>>;
  handleToAdd?: (
    to: ToData,
    { shouldApplyNewToInvoice }: { shouldApplyNewToInvoice: boolean },
  ) => void;
  handleToEdit?: (to: ToData) => void;
  initialData: ToData | null;
  isEditMode: boolean;
  formValues?: Partial<ToData>;
}

/**
 * ToDialog component for adding or editing to information.
 *
 * This dialog provides a form interface for managing to data, including:
 * - Basic information (name, address, VAT number)
 * - Contact details (email)
 * - Additional notes
 *
 * Features:
 * - Pre-fill form with current invoice values (when creating new to)
 * - Apply newly created to to current invoice
 * - Validation for duplicate to names
 * - Unsaved changes warning on dialog close
 * - Field visibility toggles for optional information
 */
export function ToDialog({
  isOpen,
  onClose,
  handleToAdd,
  handleToEdit,
  initialData,
  isEditMode,
  formValues,
}: ToDialogProps) {
  const form = useForm<ToData>({
    resolver: zodResolver(toSchema),
    defaultValues: {
      id: initialData?.id ?? "",
      name: initialData?.name ?? "",
      address: initialData?.address ?? "",
      vatNo: initialData?.vatNo ?? "",
      vatNoLabelText: initialData?.vatNoLabelText ?? "VAT no",
      email: initialData?.email ?? "",
      emailFieldIsVisible: initialData?.emailFieldIsVisible ?? true,
      vatNoFieldIsVisible: initialData?.vatNoFieldIsVisible ?? true,
      notes: initialData?.notes ?? "",
      notesFieldIsVisible: initialData?.notesFieldIsVisible ?? true,
    },
  });

  const { isDirty } = form.formState;

  const { isConfirmDiscardDialogOpen, setIsConfirmDiscardDialogOpen } =
    useConfirmDiscard();

  // by default, we want to apply the new to to the current invoice
  const [shouldApplyNewToInvoice, setShouldApplyNewToInvoice] =
    useState(true);

  // should apply inline form values to the dialog form
  const [shouldApplyInlineFormValues, setShouldApplyInlineFormValues] =
    useState(false);

  // Stores a pending action to execute after user confirms discard in the ConfirmDiscardDialog.
  // Uses currying (() => () => void) because React state setters require a function that returns
  // the new state value. When we call setPendingDiscardAction(() => closeDialog), we're storing
  // a function that returns closeDialog, not calling closeDialog immediately. This prevents
  // premature execution and allows us to defer the action until the user confirms.
  const [pendingDiscardAction, setPendingDiscardAction] = useState<
    (() => void) | null
  >(null);

  /**
   * Synchronizes form values based on the "Use current invoice data" switch state.
   *
   * When creating a new to (not in edit mode):
   * - If switch is ON: Populates the form with current invoice to data (formValues)
   *   to allow users to save the current invoice's to information as a new saved to.
   * - If switch is OFF: Resets the form to empty/default values or initialData
   *   to allow users to enter completely new to information from scratch.
   *
   * This effect does not run in edit mode to prevent overwriting the to being edited.
   */
  useEffect(() => {
    // Switch is ON: Pre-fill form with current invoice to data
    if (shouldApplyInlineFormValues && formValues && !isEditMode) {
      form.reset({
        ...form.getValues(),
        ...formValues,
      });
    }

    // Switch is OFF: Reset form to empty state or initial data
    else if (!shouldApplyInlineFormValues && !isEditMode) {
      form.reset(
        initialData ?? {
          id: "",
          name: "",
          address: "",
          vatNo: "",
          vatNoLabelText: "VAT no",
          email: "",
          emailFieldIsVisible: true,
          vatNoFieldIsVisible: true,
          notes: "",
          notesFieldIsVisible: true,
        },
      );
    }
  }, [shouldApplyInlineFormValues, formValues, initialData, isEditMode, form]);

  /**
   * Guards the pre-fill switch toggle against dirty form state.
   *
   * When the form has unsaved changes, opens the ConfirmDiscardDialog before
   * applying the switch change. Only updates shouldApplyFormValues (and thus
   * triggers form.reset via the effect above) after the user confirms discard.
   */
  function handlePrefillSwitchToggle(newValue: boolean) {
    if (isDirty) {
      setPendingDiscardAction(
        () => () => setShouldApplyInlineFormValues(newValue),
      );
      setIsConfirmDiscardDialogOpen(true);
      return;
    }
    setShouldApplyInlineFormValues(newValue);
  }

  /**
   * Closes the to dialog and resets the form to its default state.
   */
  function closeDialog() {
    form.reset();

    // by default, we don't want to apply the inline form values to the dialog form
    setShouldApplyInlineFormValues(false);
  // by default, we want to apply the new to to the current invoice
    setShouldApplyNewToInvoice(true);

    onClose(false);
  }

  function onSubmit(formValues: ToData) {
    try {
      // **RUNNING SOME VALIDATIONS FIRST**

      // Get existing tos or initialize empty array
      const tos = localStorage.getItem(TOS_LOCAL_STORAGE_KEY);
      const existingTos: unknown = tos ? JSON.parse(tos) : [];

      const rawTos = Array.isArray(existingTos) ? existingTos : [];

      const validTos: ToData[] = [];
      let hadInvalidTos = false;

      // Validate each to individually — drop only invalid items
      for (const item of rawTos) {
        const result = toSchema.safeParse(item);

        if (result.success) {
          validTos.push(result.data);
        } else {
          hadInvalidTos = true;

          console.error(
            "[to-dialog] Dropped invalid to entry:",
            result.error,
          );

          Sentry.captureException(
            new Error(
              `[to-dialog] Invalid to data in localStorage: ${rawTos.length - validTos.length} items dropped`,
            ),
          );
        }
      }

      // If we had invalid tos, save the valid tos back to localStorage
      if (hadInvalidTos) {
        localStorage.setItem(
          TOS_LOCAL_STORAGE_KEY,
          JSON.stringify(validTos),
        );
      }

      // Validate to data against existing tos
      const isDuplicateName = validTos.some(
        (to: ToData) =>
          to.name === formValues.name && to.id !== formValues.id,
      );

      if (isDuplicateName) {
        form.setError("name", {
          type: "manual",
          message: "A to with this name already exists",
        });

        // Focus on the name input field for user to fix the error
        form.setFocus("name");

        // Show error toast
        toast.error("A to with this name already exists", {
          richColors: true,
        });

        return;
      }

      if (isEditMode) {
        // Edit to
        handleToEdit?.(formValues);
      } else {
        // Add new to
        handleToAdd?.(formValues, { shouldApplyNewToInvoice });
      }

      // Close dialog
      closeDialog();
    } catch (error) {
      console.error("Failed to save to:", error);

      toast.error("Failed to save to", {
        description: "Please try again",
        richColors: true,
      });

      Sentry.captureException(error);
    }
  }

  return (
    <>
      <Dialog
        open={isOpen}
        onOpenChange={(open) => {
          if (!open) {
            // Handles the discard action by checking for unsaved changes.
            // If there are unsaved changes (isDirty), opens the confirmation dialog.
            // Otherwise, closes the dialog immediately.
            if (isDirty) {
              setPendingDiscardAction(() => closeDialog);
              setIsConfirmDiscardDialogOpen(true);
              return;
            }
            closeDialog();
          }
        }}
      >
        <DialogContent
          className="flex flex-col gap-0 overflow-y-visible p-0 sm:max-w-lg [&>button:last-child]:top-3.5"
          data-testid={`manage-to-dialog`}
        >
          <DialogHeader className="border-b border-slate-200 px-6 py-4 dark:border-slate-800">
            <DialogTitle className="text-base">
              {isEditMode ? "Edit To" : "Add New To"}
            </DialogTitle>
            <DialogDescription>
              {isEditMode
                ? "Edit the to details"
                : "Add a new to to use later in your invoices"}
            </DialogDescription>
          </DialogHeader>

          <div className="overflow-y-auto px-6 py-4">
            {/* Add Use Current Form Values switch */}
            {!isEditMode && (
              <div className="mb-4">
                <div className="flex items-center gap-2">
                  <Switch
                    checked={shouldApplyInlineFormValues}
                    onCheckedChange={handlePrefillSwitchToggle}
                    id="apply-form-values-switch"
                  />
                  <Label
                    htmlFor="apply-form-values-switch"
                    className="cursor-pointer"
                  >
                    Pre-fill with values from the current invoice form
                  </Label>
                </div>
                <span className="mt-1.5 inline-block text-xs text-slate-500">
                  When enabled, this will automatically fill in the to
                  details dialog with the information you&apos;ve already
                  entered in your current invoice form.
                </span>
              </div>
            )}

            <Form {...form}>
              <form
                onSubmit={form.handleSubmit(onSubmit)}
                className="space-y-4"
                id={TO_FORM_ID}
              >
                <FormField
                  control={form.control}
                  name="name"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>Name (Required)</FormLabel>
                      <FormControl>
                        <Textarea
                          {...field}
                          rows={3}
                          placeholder="Enter to name"
                        />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />

                <FormField
                  control={form.control}
                  name="address"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>Address (Required)</FormLabel>
                      <FormControl>
                        <Textarea
                          {...field}
                          rows={3}
                          placeholder="Enter to address"
                        />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />

                {/* Tax Number */}
                <fieldset className="rounded-md border px-4 pb-4">
                  <legend className="text-base font-semibold lg:text-lg">
                    Tax Number
                  </legend>

                  <div className="mb-2 flex items-center justify-end">
                    {/* Show Tax Number Field in PDF */}
                    <div className="flex items-center gap-2">
                      <FormField
                        control={form.control}
                        name="vatNoFieldIsVisible"
                        render={({ field }) => (
                          <FormItem>
                            <div className="flex items-center gap-2">
                              <Switch
                                checked={field.value}
                                onCheckedChange={field.onChange}
                                id="vatNoFieldIsVisible"
                                aria-label={`Show the 'Tax Number' field in the PDF`}
                              />
                              <CustomTooltip
                                trigger={
                                  <Label htmlFor="vatNoFieldIsVisible">
                                    Show in PDF
                                  </Label>
                                }
                                content='Show the "Tax Number" field in the PDF'
                                className="z-[1000]"
                              />
                            </div>
                          </FormItem>
                        )}
                      />
                    </div>
                  </div>

                  <div className="space-y-4">
                    <FormField
                      control={form.control}
                      name="vatNoLabelText"
                      render={({ field }) => (
                        <FormItem>
                          <FormLabel>Label</FormLabel>
                          <FormControl>
                            <Input
                              {...field}
                              placeholder="Enter Tax number label"
                            />
                          </FormControl>

                          {form.formState.errors.vatNoLabelText && (
                            <FormMessage>
                              {form.formState.errors.vatNoLabelText.message}
                            </FormMessage>
                          )}

                          {!form.formState.errors.vatNoLabelText && (
                            <InputHelperMessage>
                              Set a custom label (e.g. VAT no, Tax no, etc.)
                            </InputHelperMessage>
                          )}
                        </FormItem>
                      )}
                    />

                    <FormField
                      control={form.control}
                      name="vatNo"
                      render={({ field }) => (
                        <FormItem>
                          <FormLabel>Value</FormLabel>
                          <FormControl>
                            <Input
                              {...field}
                              placeholder="Enter Tax number value"
                            />
                          </FormControl>
                          <FormMessage />
                        </FormItem>
                      )}
                    />
                  </div>
                </fieldset>

                {/* Email */}
                <div className="space-y-3 rounded-md border p-4">
                  <FormField
                    control={form.control}
                    name="email"
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel className="mb-2 font-medium">
                          Email
                        </FormLabel>
                        <FormControl>
                          <Input
                            {...field}
                            type="email"
                            placeholder="to@email.com"
                          />
                        </FormControl>
                        <FormMessage />
                      </FormItem>
                    )}
                  />
                  <FormField
                    control={form.control}
                    name="emailFieldIsVisible"
                    render={({ field }) => (
                      <FormItem>
                        <div className="flex items-center gap-2">
                          <FormControl>
                            <Switch
                              checked={field.value}
                              onCheckedChange={field.onChange}
                              id="emailFieldIsVisible"
                              data-testid={`toEmailDialogFieldVisibilitySwitch`}
                              aria-label={`Show the 'Email' field in the PDF`}
                            />
                          </FormControl>
                          <CustomTooltip
                            trigger={
                              <Label htmlFor="emailFieldIsVisible">
                                Show To Email in PDF
                              </Label>
                            }
                            content='Show the "Email" field in the PDF'
                            className="z-[1000]"
                          />
                        </div>
                      </FormItem>
                    )}
                  />
                </div>

                {/* Notes */}
                <div className="space-y-3 rounded-md border p-4">
                  <FormField
                    control={form.control}
                    name="notes"
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel className="mb-2 font-medium">
                          Notes
                        </FormLabel>
                        <FormControl>
                          <Textarea
                            {...field}
                            rows={3}
                            placeholder="Enter notes (max 750 characters)"
                            maxLength={750}
                          />
                        </FormControl>
                        <FormMessage />
                      </FormItem>
                    )}
                  />
                  <FormField
                    control={form.control}
                    name="notesFieldIsVisible"
                    render={({ field }) => (
                      <FormItem>
                        <div className="flex items-center gap-2">
                          <FormControl>
                            <Switch
                              checked={field.value}
                              onCheckedChange={field.onChange}
                              id="notes-field-visibility"
                              data-testid={`toNotesDialogFieldVisibilitySwitch`}
                              aria-label={`Show the 'Notes' field in the PDF`}
                            />
                          </FormControl>
                          <CustomTooltip
                            trigger={
                              <Label htmlFor="notes-field-visibility">
                                Show To Notes in PDF
                              </Label>
                            }
                            content="Show the notes field in the PDF"
                            className="z-[1000]"
                          />
                        </div>
                      </FormItem>
                    )}
                  />
                </div>
              </form>
            </Form>

            {/* Apply to Current Invoice switch remains at bottom */}
            {!isEditMode && (
              <div className="mt-4 flex flex-col gap-1 border-t pt-4">
                <div className="flex items-center gap-2">
                  <Switch
                    checked={shouldApplyNewToInvoice}
                    onCheckedChange={setShouldApplyNewToInvoice}
                    id="apply-to-to-current-invoice-switch"
                  />
                  <Label
                    htmlFor="apply-to-to-current-invoice-switch"
                    className="cursor-pointer"
                  >
                    Apply to Current Invoice
                  </Label>
                </div>
                <span className="mt-1.5 text-xs text-slate-500">
                  When enabled, the newly created to will be automatically
                  applied to your current invoice form and reflected in the
                  generated PDF.
                </span>
              </div>
            )}
          </div>
          <DialogFooter className="border-border border-t px-6 py-4">
            <Button
              type="button"
              variant="outline"
              onClick={() => {
                // Handles the discard action by checking for unsaved changes.
                // If there are unsaved changes (isDirty), opens the confirmation dialog.
                // Otherwise, closes the dialog immediately.
                if (isDirty) {
                  setPendingDiscardAction(() => closeDialog);
                  setIsConfirmDiscardDialogOpen(true);
                  return;
                }
                closeDialog();
              }}
            >
              Cancel
            </Button>
            <Button
              type="button"
              onClick={async () => {
                // trigger validations and submit the form and handle errors
                void form.handleSubmit(onSubmit)();
              }}
              form={TO_FORM_ID}
            >
              Save To
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
      <ConfirmDiscardDialog
        open={isConfirmDiscardDialogOpen}
        onOpenChange={setIsConfirmDiscardDialogOpen}
        onDiscard={() => {
          pendingDiscardAction?.();
          setPendingDiscardAction(null);
        }}
        entityName="to"
      />
    </>
  );
}
