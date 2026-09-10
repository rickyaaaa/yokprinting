{{-- A stored invoice and a draft preview print from the same template; only
     the way their data is fetched differs, and BuildInvoiceDocument settles
     that before either reaches here. --}}
@include('pdf.invoices.preview', ['document' => $document])
