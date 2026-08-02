// `BookingProvider` / `useBooking` portaient l'état de l'assistant en cinq étapes,
// supprimé avec lui. Les hooks ci-dessous servent les écrans vivants.
export {
  useServiceCatalog,
  usePricingServices,
  useBrowseProviders,
  useEligibleCompanies,
  useAddressAutocomplete,
  usePostalAutocomplete,
  useCreateBooking,
  useBookings,
  useBookingDetail,
  useBookingFavorites,
} from './hooks';
export type {
  ServiceCategory,
  Service,
  Provider,
  EligibleCompany,
  Booking,
  BookingState,
  ProviderTypePreference,
  BookingFavoriteSummary,
} from './types';
