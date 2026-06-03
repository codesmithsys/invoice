import { Plus, Trash2, Pencil, AlertCircleIcon } from "lucide-react";

import {
  useId,
  useState,
  useEffect,
  type Dispatch,
  type SetStateAction,
} from "react";
import { CustomTooltip } from "@/components/ui/tooltip";
import { SelectNative } from "@/components/ui/select-native";
import { Button } from "@/components/ui/button";
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
import { fromSchema, type InvoiceData, type FromData } from "@/app/schema";
import { toast } from "sonner";
import { cn } from "@/lib/utils";
import { Label } from "@/components/ui/label";
import { isLocalStorageAvailable } from "@/lib/check-local-storage";
import { umamiTrackEvent } from "@/lib/umami-analytics-track-event";
import * as Sentry from "@sentry/nextjs";
import { DEFAULT_FROM_DATA } from "@/app/constants";
import { FromDialog } from "@/app/(app)/components/invoice-form/sections/components/seller/seller-dialog";

export const FROMS_LOCAL_STORAGE_KEY = "EASY_INVOICE_PDF_SELLERS";

interface FromManagementProps {
  setValue: UseFormSetValue<InvoiceData>;
  invoiceData: InvoiceData;
  selectedFromId: string;
  setSelectedFromId: Dispatch<SetStateAction<string>>;
  formValues?: Partial<FromData>;
  isMobile: boolean;
}

/**
 * FromManagement Component
 *
 * Manages seller data for invoices including:
 * - Loading and displaying saved sellers from localStorage
 * - Creating new sellers via a dialog form
 * - Editing existing seller details
 * - Deleting sellers with confirmation
 * - Auto-populating invoice form fields when a seller is selected
 *
 * When a seller is selected from the dropdown, their details are populated into the
 * invoice form and the form fields become read-only. Users must use the Edit Seller
 * button to modify saved seller information.
 *
 * @param setValue - React Hook Form setter to update invoice form values
 * @param invoiceData - Current invoice data including seller information
 * @param selectedFromId - ID of the currently selected seller
 * @param setSelectedFromId - Callback to update the selected seller ID
 * @param formValues - Current seller form values (optional)
 */
export function FromManagement({
  setValue,
  invoiceData,
  selectedFromId,
  setSelectedFromId,
  formValues,
  isMobile,
}: FromManagementProps) {
  const [isFromDialogOpen, setIsFromDialogOpen] = useState(false);
  const [isDeleteDialogOpen, setIsDeleteDialogOpen] = useState(false);

  // State to store the list of saved froms for the dropdown selection.
  const [sellersSelectOptions, setSellersSelectOptions] = useState<
    FromData[]
  >([]);

  // State to track the from currently being edited (null if not editing).
  const [editingSeller, setEditingSeller] = useState<FromData | null>(null);

  const sellerSelectId = useId();

  const isEditMode = Boolean(editingSeller);

  // Load froms from localStorage on component mount
  useEffect(() => {
    try {
      const savedSellers = localStorage.getItem(FROMS_LOCAL_STORAGE_KEY);
      const parsedSellers: unknown = savedSellers
        ? JSON.parse(savedSellers)
        : [];

      const rawSellers = Array.isArray(parsedSellers) ? parsedSellers : [];

      const validSellers: FromData[] = [];
      const invalidSellers: FromData[] = [];

      // Validate each from individually — drop only invalid items
      for (const item of rawSellers) {
        const result = fromSchema.safeParse(item);
        if (result.success) {
          validSellers.push(result.data);
        } else {
          invalidSellers.push(item as FromData);

          console.error(
            "[seller-management] Invalid seller entry:",
            result.error,
          );
        }
      }

      // If we have invalid froms, drop them and save the valid froms back to localStorage
      if (invalidSellers.length > 0) {
        console.error(
          `[seller-management] Dropped ${invalidSellers.length} invalid seller entries:`,
          invalidSellers,
        );

        Sentry.captureException(
          new Error(
            `[seller-management] Invalid seller data in localStorage: ${rawSellers.length - validSellers.length} items dropped`,
          ),
        );

        localStorage.setItem(
          FROMS_LOCAL_STORAGE_KEY,
          JSON.stringify(validSellers),
        );
      }

      const selectedFrom = validSellers.find((seller: FromData) => {
        return seller?.id === invoiceData?.seller?.id;
      });

      setSellersSelectOptions(validSellers);
      setSelectedFromId(selectedFrom?.id ?? "");
    } catch (error) {
      console.error("Failed to load sellers:", error);

      Sentry.captureException(error);
    }
  }, [invoiceData?.seller?.id, setSelectedFromId]);

  // Update froms when a new one is added
  const handleSellerAdd = (
    newSeller: FromData,
    {
      shouldApplyNewSellerToInvoice,
    }: { shouldApplyNewSellerToInvoice: boolean },
  ) => {
    try {
      const newSellerWithId = {
        ...newSeller,
        // Generate a unique ID for the new from (IMPORTANT!) =)
        id: Date.now().toString(),
      } satisfies FromData;

      const newSellers = [...sellersSelectOptions, newSellerWithId];

      // Save to localStorage
      localStorage.setItem(
        FROMS_LOCAL_STORAGE_KEY,
        JSON.stringify(newSellers),
      );

      // Update the sellers state
      setSellersSelectOptions(newSellers);

      // Apply the new from to the invoice if the user wants to, otherwise just add it to the list and use it later if needed
      if (shouldApplyNewSellerToInvoice) {
        setValue("seller", newSellerWithId);
        setSelectedFromId(newSellerWithId?.id);
      }

      toast.success(
        shouldApplyNewSellerToInvoice
          ? "From added and applied to invoice"
          : "From added successfully",
        {
          id: "add_seller_success_toast",
          richColors: true,
          position: isMobile ? "top-center" : "bottom-right",
        },
      );

      // analytics track event
      umamiTrackEvent("add_from_success");
    } catch (error) {
      console.error("Failed to add seller:", error);

      toast.error("Failed to add from", {
        id: "add_from_error_toast",
        description: "Please try again",
        closeButton: true,
        position: isMobile ? "top-center" : "bottom-right",
      });

      Sentry.captureException(error);
    }
  };

  // Update froms when edited
  const handleSellerEdit = (editedSeller: FromData) => {
    try {
      const updatedSellers = sellersSelectOptions.map((seller) =>
        seller.id === editedSeller.id ? editedSeller : seller,
      );

      localStorage.setItem(
        FROMS_LOCAL_STORAGE_KEY,
        JSON.stringify(updatedSellers),
      );

      setSellersSelectOptions(updatedSellers);
      setValue("seller", editedSeller);

      // end edit mode
      setEditingSeller(null);

      toast.success("From updated successfully", {
        id: "edit_from_success_toast",
        richColors: true,
        position: isMobile ? "top-center" : "bottom-right",
      });

      // analytics track event
      umamiTrackEvent("edit_from_success");
    } catch (error) {
      console.error("Failed to edit seller:", error);

      toast.error("Failed to edit from", {
        id: "edit_from_error_toast",
        description: "Please try again",
        closeButton: true,
        position: isMobile ? "top-center" : "bottom-right",
      });

      Sentry.captureException(error);
    }
  };

  const handleSellerChange = (event: React.ChangeEvent<HTMLSelectElement>) => {
    const id = event.target.value;

    if (id) {
      setSelectedFromId(id);
      const selectedSeller = sellersSelectOptions.find(
        (seller) => seller.id === id,
      );

      if (selectedSeller) {
        setValue("seller", selectedSeller);
        toast.success(`From "${selectedSeller.name}" applied to invoice`, {
          id: "change_from_success_toast",
          richColors: true,
          position: isMobile ? "top-center" : "bottom-right",
        });
      }
    } else {
      // Clear the from from the form if the user selects the empty option
      setSelectedFromId("");
      setValue("seller", DEFAULT_FROM_DATA);

      toast.success("From restored to default", {
        id: "reset_from_success_toast",
        richColors: true,
        position: isMobile ? "top-center" : "bottom-right",
      });
    }

    // analytics track event
    umamiTrackEvent("change_from");
  };

  const handleDeleteSeller = () => {
    try {
      setSellersSelectOptions((prevSellers) => {
        const updatedSellers = prevSellers.filter(
          (seller) => seller.id !== selectedFromId,
        );

        localStorage.setItem(
          FROMS_LOCAL_STORAGE_KEY,
          JSON.stringify(updatedSellers),
        );
        return updatedSellers;
      });
      // Clear the selected from index
      setSelectedFromId("");
      // Clear the from from the form if it was selected
      setValue("seller", DEFAULT_FROM_DATA);

      // Close the delete dialog
      setIsDeleteDialogOpen(false);

      toast.success("From deleted successfully", {
        id: "delete_from_success_toast",
        richColors: true,
        position: isMobile ? "top-center" : "bottom-right",
      });

      // analytics track event
      umamiTrackEvent("delete_from_success");
    } catch (error) {
      console.error("Failed to delete seller:", error);

      toast.error("Failed to delete from", {
        id: "delete_from_error_toast",
        description: "Please try again",
        closeButton: true,
        position: isMobile ? "top-center" : "bottom-right",
      });

      Sentry.captureException(error);
    }
  };

  const activeSeller = sellersSelectOptions.find(
    (seller) => seller.id === selectedFromId,
  );

  const hasSellers = sellersSelectOptions.length > 0;

  return (
    <>
      <div
        className={cn(
          "flex w-full flex-col gap-2",
          hasSellers
            ? "rounded-md border p-4 shadow shadow-slate-400/10"
            : "mt-3",
        )}
      >
        {hasSellers ? (
          <div className="w-full space-y-1">
            <div className="flex items-center gap-1">
              <Label htmlFor={sellerSelectId} className="">
                Select From
              </Label>
            </div>
            <div className="flex w-full gap-2">
              <SelectNative
                id={sellerSelectId}
                className={cn(
                  "block h-8 w-full text-[12px]",
                  !selectedFromId && "italic text-gray-700",
                )}
                onChange={handleSellerChange}
                value={selectedFromId}
                title={activeSeller?.name}
              >
                <option value="">No from selected (default)</option>
                {sellersSelectOptions.map((seller) => (
                  <option key={seller.id} value={seller.id}>
                    {seller.name}
                  </option>
                ))}
              </SelectNative>

              {selectedFromId ? (
                <div className="flex items-center gap-2">
                  <CustomTooltip
                    trigger={
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => {
                          if (activeSeller) {
                            // dismiss any existing toast for better UX
                            toast.dismiss();

                            setEditingSeller(activeSeller);
                            setIsFromDialogOpen(true);
                          }
                        }}
                        className="size-8 px-2"
                      >
                        <span className="sr-only">Edit from</span>
                        <Pencil className="size-3.5" />
                      </Button>
                    }
                    content="Edit from"
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
                        <span className="sr-only">Delete from</span>
                        <Trash2 className="size-3.5" />
                      </Button>
                    }
                    content="Delete from"
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

                  // open from dialog
                  setIsFromDialogOpen(true);
                } else {
                  toast.error("Unable to add from", {
                    id: "unable-to-add-from-error-toast",
                    closeButton: true,
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
              New From
              <Plus className="ml-1 size-3" />
            </Button>
          }
          content={
            isLocalStorageAvailable ? (
              <div className="flex items-center gap-3 p-2">
                <div className="space-y-1">
                  <p className="text-sm font-semibold text-slate-900">
                    Save Froms for Quick Access
                  </p>
                  <p className="text-pretty text-xs leading-relaxed text-slate-700">
                    Store multiple froms to easily reuse their information in
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
                    enable it or try another browser to save from information.
                  </p>
                </div>
              </div>
            )
          }
        />
      </div>

      <FromDialog
        // we need to rerender the dialog when the editingSeller changes
        key={editingSeller?.id}
        isOpen={isFromDialogOpen}
        onClose={() => {
          setIsFromDialogOpen(false);
          setEditingSeller(null);
        }}
        handleSellerAdd={handleSellerAdd}
        handleSellerEdit={handleSellerEdit}
        initialData={editingSeller}
        isEditMode={isEditMode}
        formValues={formValues}
      />

      {/* Delete alert seller dialog */}
      <AlertDialog
        open={isDeleteDialogOpen}
        onOpenChange={setIsDeleteDialogOpen}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete From</AlertDialogTitle>
            <AlertDialogDescription>
              Are you sure you want to delete{" "}
              <span className="font-bold">
                &quot;{activeSeller?.name}&quot;
              </span>{" "}
              from? This action cannot be undone.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={handleDeleteSeller}
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
