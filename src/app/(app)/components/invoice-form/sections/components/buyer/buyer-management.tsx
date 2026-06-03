import { Plus, Trash2, Pencil, AlertCircleIcon } from "lucide-react";
import { useId, useState, useEffect } from "react";
import { CustomTooltip } from "@/components/ui/tooltip";
import { SelectNative } from "@/components/ui/select-native";
import { Button } from "@/components/ui/button";
import { ToDialog } from "./buyer-dialog";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import type { UseFormSetValue } from "react-hook-form";
import { toSchema, type InvoiceData, type ToData } from "@/app/schema";
import { toast } from "sonner";
import { cn } from "@/lib/utils";
import { Label } from "@/components/ui/label";
import { isLocalStorageAvailable } from "@/lib/check-local-storage";
import { umamiTrackEvent } from "@/lib/umami-analytics-track-event";
import * as Sentry from "@sentry/nextjs";
import { DEFAULT_TO_DATA } from "@/app/constants";

export const TOS_LOCAL_STORAGE_KEY = "EASY_INVOICE_PDF_BUYERS";

interface ToManagementProps {
  setValue: UseFormSetValue<InvoiceData>;
  invoiceData: InvoiceData;
  selectedToId: string;
  setSelectedToId: (id: string) => void;
  formValues?: Partial<ToData>;
  isMobile: boolean;
}

/**
 * ToManagement Component
 *
 * Manages to data for invoices including:
 * - Loading and displaying saved tos from localStorage
 * - Creating new tos via a dialog form
 * - Editing existing to details
 * - Deleting tos with confirmation
 * - Auto-populating invoice form fields when a to is selected
 *
 * When a to is selected from the dropdown, their details are populated into the
 * invoice form and the form fields become read-only. Users must use the Edit To
 * button to modify saved to information.
 *
 * @param setValue - React Hook Form setter to update invoice form values
 * @param invoiceData - Current invoice data including to information
 * @param selectedToId - ID of the currently selected to
 * @param setSelectedToId - Callback to update the selected to ID
 * @param formValues - Current to form values (optional)
 */
export function ToManagement({
  setValue,
  invoiceData,
  selectedToId,
  setSelectedToId,
  formValues,
  isMobile,
}: ToManagementProps) {
  const [isToDialogOpen, setIsToDialogOpen] = useState(false);
  const [isDeleteDialogOpen, setIsDeleteDialogOpen] = useState(false);

  // State to store the list of saved buyers for the dropdown selection.
  const [tosSelectOptions, setTosSelectOptions] = useState<ToData[]>(
    [],
  );

  // State to track the to currently being edited (null if not editing).
  const [editingTo, setEditingTo] = useState<ToData | null>(null);

  const toSelectId = useId();

  const isEditMode = Boolean(editingTo);

  // Load buyers from localStorage on component mount
  useEffect(() => {
    try {
      const savedBuyers = localStorage.getItem(TOS_LOCAL_STORAGE_KEY);
      const parsedBuyers: unknown = savedBuyers ? JSON.parse(savedBuyers) : [];

      const rawBuyers = Array.isArray(parsedBuyers) ? parsedBuyers : [];

      const validBuyers: ToData[] = [];
      const invalidBuyers: ToData[] = [];

      // Validate each to individually — drop only invalid items
      for (const item of rawBuyers) {
        const result = toSchema.safeParse(item);
        if (result.success) {
          validBuyers.push(result.data);
        } else {
          invalidBuyers.push(item as ToData);

          console.error(
            "[to-management] Invalid to entry:",
            result.error,
          );
        }
      }

      // If we have invalid buyers, drop them and save the valid buyers back to localStorage
      if (invalidBuyers.length > 0) {
        console.error(
          `[to-management] Dropped ${invalidBuyers.length} invalid to entries:`,
          invalidBuyers,
        );

        Sentry.captureException(
          new Error(
            `[to-management] Invalid to data in localStorage: ${rawBuyers.length - validBuyers.length} items dropped`,
          ),
        );

        localStorage.setItem(
          TOS_LOCAL_STORAGE_KEY,
          JSON.stringify(validBuyers),
        );
      }

      const selectedTo = validBuyers.find((to: ToData) => {
        return to?.id === invoiceData?.buyer?.id;
      });

      setTosSelectOptions(validBuyers);
      setSelectedToId(selectedTo?.id ?? "");
    } catch (error) {
      console.error("Failed to load buyers:", error);

      Sentry.captureException(error);
    }
  }, [invoiceData?.buyer?.id, setSelectedToId]);

  // Update buyers when a new one is added
  const handleToAdd = (
    newTo: ToData,
    { shouldApplyNewToInvoice }: { shouldApplyNewToInvoice: boolean },
  ) => {
    try {
      const newToWithId = {
        ...newTo,
        // Generate a unique ID for the new to (IMPORTANT!) =)
        id: Date.now().toString(),
      } satisfies ToData;

      const newBuyers = [...tosSelectOptions, newToWithId];

      // Save to localStorage
      localStorage.setItem(TOS_LOCAL_STORAGE_KEY, JSON.stringify(newBuyers));

      // Update the buyers state
      setTosSelectOptions(newBuyers);

      // Apply the new to to the invoice if the user wants to, otherwise just add it to the list and use it later if needed
      if (shouldApplyNewToInvoice) {
        setValue("buyer", newToWithId);
        setSelectedToId(newToWithId?.id);
      }

      toast.success(
        shouldApplyNewToInvoice
          ? "To added and applied to invoice"
          : "To added successfully",
        {
          id: "add_buyer_success_toast",
          richColors: true,
          position: isMobile ? "top-center" : "bottom-right",
        },
      );

      // analytics track event
      umamiTrackEvent("add_buyer_success");
    } catch (error) {
      console.error("Failed to add to:", error);

      toast.error("Failed to add to", {
        id: "add_buyer_error_toast",
        description: "Please try again",
        closeButton: true,
        position: isMobile ? "top-center" : "bottom-right",
      });

      Sentry.captureException(error);
    }
  };

  // Update buyers when edited
  const handleToEdit = (editedTo: ToData) => {
    try {
      const updatedBuyers = tosSelectOptions.map((to) =>
        to.id === editedTo.id ? editedTo : to,
      );

      localStorage.setItem(
        TOS_LOCAL_STORAGE_KEY,
        JSON.stringify(updatedBuyers),
      );

      setTosSelectOptions(updatedBuyers);
      setValue("buyer", editedTo);

      // end edit mode
      setEditingTo(null);

      toast.success("To updated successfully", {
        id: "edit_buyer_success_toast",
        richColors: true,
        position: isMobile ? "top-center" : "bottom-right",
      });

      // analytics track event
      umamiTrackEvent("edit_buyer_success");
    } catch (error) {
      console.error("Failed to edit to:", error);

      toast.error("Failed to edit to", {
        id: "edit_buyer_error_toast",
        description: "Please try again",
        closeButton: true,
        position: isMobile ? "top-center" : "bottom-right",
      });

      Sentry.captureException(error);
    }
  };

  const handleToChange = (event: React.ChangeEvent<HTMLSelectElement>) => {
    const id = event.target.value;

    if (id) {
      setSelectedToId(id);
      const selectedTo = tosSelectOptions.find(
        (to) => to.id === id,
      );

      if (selectedTo) {
        setValue("buyer", selectedTo);
        toast.success(`To "${selectedTo.name}" applied to invoice`, {
          id: "change_buyer_success_toast",
          richColors: true,
          position: isMobile ? "top-center" : "bottom-right",
        });
      }
    } else {
      // Clear the to from the form if the user selects the empty option
      setSelectedToId("");
      setValue("buyer", DEFAULT_TO_DATA);

      toast.success("To restored to default", {
        id: "reset_buyer_success_toast",
        richColors: true,
        position: isMobile ? "top-center" : "bottom-right",
      });
    }

    // analytics track event
    umamiTrackEvent("change_buyer");
  };

  const handleDeleteTo = () => {
    try {
      setTosSelectOptions((prevBuyers) => {
        const updatedBuyers = prevBuyers.filter(
          (to) => to.id !== selectedToId,
        );

        localStorage.setItem(
          TOS_LOCAL_STORAGE_KEY,
          JSON.stringify(updatedBuyers),
        );
        return updatedBuyers;
      });
      // Clear the selected to index
      setSelectedToId("");
      // Clear the to from the form if it was selected
      setValue("buyer", DEFAULT_TO_DATA);

      // Close the delete dialog
      setIsDeleteDialogOpen(false);

      toast.success("To deleted successfully", {
        id: "delete_buyer_success_toast",
        richColors: true,
        position: isMobile ? "top-center" : "bottom-right",
      });

      // analytics track event
      umamiTrackEvent("delete_buyer_success");
    } catch (error) {
      console.error("Failed to delete to:", error);

      toast.error("Failed to delete to", {
        id: "delete_buyer_error_toast",
        description: "Please try again",
        closeButton: true,
        position: isMobile ? "top-center" : "bottom-right",
      });

      Sentry.captureException(error);
    }
  };

  const activeTo = tosSelectOptions.find(
    (to) => to.id === selectedToId,
  );

  const hasTos = tosSelectOptions.length > 0;

  return (
    <>
      <div
        className={cn(
          "flex w-full flex-col gap-2",
          hasTos
            ? "rounded-md border p-4 shadow shadow-slate-400/10"
            : "mt-3",
        )}
      >
        {hasTos ? (
          <div className="w-full space-y-1">
            <div className="flex items-center gap-1">
              <Label htmlFor={toSelectId} className="">
                Select To
              </Label>
            </div>
            <div className="flex w-full gap-2">
              <SelectNative
                id={toSelectId}
                className={cn(
                  "block h-8 w-full text-[12px]",
                  !selectedToId && "italic text-gray-700",
                )}
                onChange={handleToChange}
                value={selectedToId}
                title={activeTo?.name}
              >
                <option value="">No to selected (default)</option>
                {tosSelectOptions.map((to) => (
                  <option key={to.id} value={to.id}>
                    {to.name}
                  </option>
                ))}
              </SelectNative>

              {selectedToId ? (
                <div className="flex items-center gap-2">
                  <CustomTooltip
                    trigger={
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => {
                          if (activeTo) {
                            // dismiss any existing toast for better UX
                            toast.dismiss();

                            setEditingTo(activeTo);
                            setIsToDialogOpen(true);
                          }
                        }}
                        className="size-8 px-2"
                      >
                        <span className="sr-only">Edit to</span>
                        <Pencil className="size-3.5" />
                      </Button>
                    }
                    content="Edit to"
                  />
                  <CustomTooltip
                    trigger={
                      <Button
                        variant="destructive"
                        size="sm"
                        onClick={() => {
                          // dismiss any existing toast for better UX
                          toast.dismiss();

                          setIsDeleteDialogOpen(true);
                        }}
                        className="size-8 px-2"
                      >
                        <span className="sr-only">Delete to</span>
                        <Trash2 className="size-3.5" />
                      </Button>
                    }
                    content="Delete to"
                  />
                </div>
              ) : null}
            </div>
          </div>
        ) : null}

        <CustomTooltip
          side="bottom"
          className={cn(!isLocalStorageAvailable && "bg-red-50")}
          trigger={
            <Button
              variant="default"
              size="sm"
              onClick={() => {
                if (isLocalStorageAvailable) {
                  // dismiss any existing toast for better UX
                  toast.dismiss();

                  // open to dialog
                  setIsToDialogOpen(true);
                } else {
                  toast.error("Unable to add to", {
                    id: "unable-to-add-to-error-toast",
                    description: (
                      <>
                        <p className="text-pretty text-xs leading-relaxed text-red-700">
                          Local storage is not available in your browser. Please
                          enable it or try another browser.
                        </p>
                      </>
                    ),
                    position: isMobile ? "top-center" : "bottom-right",
                  });
                }
              }}
              aria-disabled={!isLocalStorageAvailable} // better UX than 'disabled'
            >
              New To
              <Plus className="ml-1 size-3" />
            </Button>
          }
          content={
            isLocalStorageAvailable ? (
              <div className="flex items-center gap-3 p-2">
                <div className="space-y-1">
                  <p className="text-sm font-semibold text-slate-900">
                    Save Tos for Quick Access
                  </p>
                  <p className="text-pretty text-xs leading-relaxed text-slate-700">
                    Store multiple tos to easily reuse their information in
                    future invoices. All data is saved locally in your browser.
                  </p>
                </div>
              </div>
            ) : (
              <div className="flex items-center gap-3 bg-red-50 p-3">
                <AlertCircleIcon className="h-5 w-5 flex-shrink-0 fill-red-600 text-white" />
                <div className="space-y-1">
                  <p className="text-sm font-semibold text-red-800">
                    Storage Not Available
                  </p>
                  <p className="text-pretty text-xs leading-relaxed text-red-700">
                    Local storage is not available in your browser. Please
                    enable it or try another browser to save to information.
                  </p>
                </div>
              </div>
            )
          }
        />
      </div>

      <ToDialog
        // we need to rerender the dialog when the editingTo changes
        key={editingTo?.id}
        isOpen={isToDialogOpen}
        onClose={() => {
          setIsToDialogOpen(false);
          setEditingTo(null);
        }}
        handleToAdd={handleToAdd}
        handleToEdit={handleToEdit}
        initialData={editingTo}
        isEditMode={isEditMode}
        formValues={formValues}
      />

      {/* Delete alert to dialog */}
      <AlertDialog
        open={isDeleteDialogOpen}
        onOpenChange={setIsDeleteDialogOpen}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete To</AlertDialogTitle>
            <AlertDialogDescription>
              Are you sure you want to delete{" "}
              <span className="font-bold">&quot;{activeTo?.name}&quot;</span>{" "}
              to? This action cannot be undone.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={handleDeleteTo}
              className="bg-red-500 text-red-50 hover:bg-red-500/90"
            >
              Delete
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}
