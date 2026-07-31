<?php ?>
                <!-- ---------------------------------------------------- -->
                <!-- INVOICES UI -->
                <!-- ---------------------------------------------------- -->
                <div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden">
                    <div class="p-6 border-b flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-slate-50/50">
                        <div class="relative w-full sm:w-72">
                            <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                            <input type="text" id="searchInput" onkeyup="searchTable()" placeholder="Search invoices..." class="w-full pl-10 pr-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 outline-none bg-white">
                        </div>
                        <?php if (has_permission('can_add_sale')): ?>
                            <button onclick="document.getElementById('invModal').classList.remove('hidden')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-md flex items-center w-full sm:w-auto justify-center transition"><i class="fa-solid fa-plus mr-2"></i> Create Invoice</button>
                        <?php endif; ?>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-slate-600" id="dataTable">
                            <thead class="bg-white text-slate-400 uppercase tracking-wider text-xs border-b">
                                <tr>
                                    <th class="px-6 py-4 font-bold">Invoice #</th>
                                    <th class="px-6 py-4 font-bold">Customer</th>
                                    <th class="px-6 py-4 font-bold">Date</th>
                                    <th class="px-6 py-4 font-bold">Grand Total</th>
                                    <th class="px-6 py-4 font-bold text-emerald-500">Paid</th>
                                    <th class="px-6 py-4 font-bold text-rose-500">Due</th>
                                    <th class="px-6 py-4 font-bold text-indigo-500">Reference</th>
                                    <th class="px-6 py-4 font-bold text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach($records as $row): ?>
                                    <tr class="hover:bg-slate-50 transition-colors">
                                        <td class="px-6 py-4 font-extrabold text-indigo-600">
                                            <a href="/app/query_history?table=invoices&id=<?= $row['id'] ?>" class="hover:underline"><?= $row['invoice_number'] ?></a>
                                        </td>
                                        <td class="px-6 py-4 font-bold text-slate-800"><?= xss_clean($row['customer_name']) ?></td>
                                        <td class="px-6 py-4 font-medium"><?= date('d M, Y', strtotime($row['issue_date'])) ?></td>
                                        <td class="px-6 py-4 font-extrabold text-slate-800"><?= $currencySymbol ?> <?= number_format($row['grand_total'], 2) ?></td>
                                        <td class="px-6 py-4 font-bold text-emerald-500"><?= $currencySymbol ?> <?= number_format($row['paid_amount'], 2) ?></td>
                                        <td class="px-6 py-4 font-bold text-rose-500"><?= $currencySymbol ?> <?= number_format($row['due_amount'], 2) ?></td>
                                        <td class="px-6 py-4 font-bold text-slate-800">
                                            <?= $row['reference_staff_id'] ? '<i class="fa-solid fa-user-tie text-indigo-400 mr-1"></i> '.xss_clean($row['reference_name']) : '<span class="text-slate-400 text-xs">System / None</span>' ?>
                                        </td>
                                        <td class="px-6 py-4 text-right whitespace-nowrap">
                                            <button onclick="generatePDF('<?= base64_encode(json_encode($row)) ?>')" class="text-indigo-600 bg-indigo-50 px-4 py-2 rounded-lg hover:bg-indigo-100 font-bold mr-2 transition"><i class="fa-solid fa-file-pdf mr-1"></i> PDF</button>
                                            <?php if (has_permission('can_delete_sale')): ?>
                                                <a href="/app?action=delete&table=invoices&id=<?= $row['id'] ?>" onclick="return confirm('Delete this invoice?')" class="text-rose-500 bg-rose-50 px-4 py-2 rounded-lg hover:bg-rose-100 font-bold transition"><i class="fa-solid fa-trash"></i></a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if(empty($records)): ?>
                                    <tr><td colspan="8" class="p-12 text-center text-slate-400 font-medium">No invoices generated yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Invoice Creation Modal -->
                <div id="invModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center z-50 p-4">
                    <div class="bg-white w-full max-w-4xl max-h-[90vh] overflow-y-auto rounded-2xl shadow-2xl p-6 sm:p-8 custom-scrollbar">
                        <div class="flex justify-between items-center mb-6 border-b border-slate-100 pb-4">
                            <h2 class="text-2xl font-extrabold text-slate-800 flex items-center"><i class="fa-solid fa-file-invoice-dollar text-indigo-500 mr-3"></i> Generate New Invoice</h2>
                            <button onclick="document.getElementById('invModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-700 bg-slate-100 w-8 h-8 rounded-full flex items-center justify-center transition"><i class="fa-solid fa-times"></i></button>
                        </div>
                        <form method="POST" action="" class="space-y-6">
                            <input type="hidden" name="action" value="save_invoice">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <div><label class="block text-sm font-bold text-slate-700 mb-2">Issue Date</label><input type="date" name="issue_date" required value="<?= date('Y-m-d') ?>" class="w-full border border-slate-200 p-3 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none transition"></div>
                                <div><label class="block text-sm font-bold text-slate-700 mb-2">Customer Name</label><input type="text" name="customer_name" required class="w-full border border-slate-200 p-3 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none transition"></div>
                                <div><label class="block text-sm font-bold text-slate-700 mb-2">Mobile Number</label><input type="text" name="mobile" class="w-full border border-slate-200 p-3 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none transition"></div>
                                <div><label class="block text-sm font-bold text-slate-700 mb-2">Email Address</label><input type="email" name="email" class="w-full border border-slate-200 p-3 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none transition"></div>
                                <div class="col-span-1 sm:col-span-2"><label class="block text-sm font-bold text-slate-700 mb-2">Service Description</label><input type="text" name="service_desc" required placeholder="e.g. Dubai Visa Processing + Air Ticket" class="w-full border border-slate-200 p-3 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none transition"></div>
                            </div>
                            
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 bg-slate-50 border border-slate-200 p-5 rounded-2xl">
                                <div><label class="block text-xs font-extrabold text-slate-500 uppercase tracking-wider mb-2">Unit Price (<?= $currencySymbol ?>)</label><input type="number" id="iprice" name="unit_price" value="0" step="0.01" oninput="calcInv()" class="w-full border border-slate-200 p-3 rounded-xl font-bold text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500"></div>
                                <div><label class="block text-xs font-extrabold text-slate-500 uppercase tracking-wider mb-2">Quantity</label><input type="number" id="iqty" name="quantity" value="1" oninput="calcInv()" class="w-full border border-slate-200 p-3 rounded-xl font-bold text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500"></div>
                                <div><label class="block text-xs font-extrabold text-slate-500 uppercase tracking-wider mb-2">Discount (<?= $currencySymbol ?>)</label><input type="number" id="idisc" name="discount" value="0" step="0.01" oninput="calcInv()" class="w-full border border-slate-200 p-3 rounded-xl font-bold text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500"></div>
                                <div><label class="block text-xs font-extrabold text-slate-500 uppercase tracking-wider mb-2">Tax (<?= $currencySymbol ?>)</label><input type="number" id="itax" name="tax" value="0" step="0.01" oninput="calcInv()" class="w-full border border-slate-200 p-3 rounded-xl font-bold text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500"></div>
                            </div>

                            <div class="bg-indigo-50 border border-indigo-100 p-5 rounded-2xl flex flex-col sm:flex-row items-center gap-6">
                                <div class="flex-1 w-full"><label class="block text-xs font-extrabold text-indigo-800 uppercase tracking-wider mb-2">Amount Paid by Customer (<?= $currencySymbol ?>)</label><input type="number" id="ipaid" name="paid_amount" value="0" step="0.01" oninput="calcInv()" class="w-full border border-indigo-200 p-3 rounded-xl font-bold text-indigo-700 outline-none focus:ring-2 focus:ring-indigo-500 bg-white shadow-sm"></div>
                                <div class="flex-1 w-full text-right bg-white p-4 rounded-xl border border-indigo-100 shadow-sm">
                                    <p class="text-xs font-extrabold text-rose-500 uppercase tracking-wider mb-1">Due Amount</p>
                                    <p class="text-2xl font-extrabold text-rose-600"><?= $currencySymbol ?> <span id="idue">0.00</span></p>
                                    <input type="hidden" name="due_amount" id="idue_val">
                                </div>
                            </div>
                            
                            <div class="flex flex-col sm:flex-row justify-between items-center pt-6 border-t border-slate-100 mt-6 gap-4">
                                <button type="button" onclick="document.getElementById('invModal').classList.add('hidden')" class="w-full sm:w-auto px-6 py-3 border border-slate-200 rounded-xl font-bold text-slate-600 hover:bg-slate-50 transition">Cancel</button>
                                <div class="flex flex-col sm:flex-row items-center gap-6 w-full sm:w-auto">
                                    <div class="text-center sm:text-right w-full sm:w-auto">
                                        <p class="text-sm text-slate-500 font-bold">Subtotal: <?= $currencySymbol ?> <span id="isub">0.00</span></p>
                                        <input type="hidden" name="subtotal" id="isub_val">
                                        <p class="text-3xl font-extrabold text-slate-800">Total: <?= $currencySymbol ?> <span id="igrand">0.00</span></p>
                                        <input type="hidden" name="grand_total" id="igrand_val">
                                    </div>
                                    <button type="submit" class="w-full sm:w-auto bg-indigo-600 text-white px-8 py-4 rounded-xl font-extrabold hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition">Generate Invoice</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <script>
                    function calcInv() {
                        const p = parseFloat(document.getElementById('iprice').value) || 0;
                        const q = parseFloat(document.getElementById('iqty').value) || 0;
                        const d = parseFloat(document.getElementById('idisc').value) || 0;
                        const t = parseFloat(document.getElementById('itax').value) || 0;
                        const paid = parseFloat(document.getElementById('ipaid').value) || 0;
                        
                        const sub = p * q;
                        const grand = sub - d + t;
                        const due = grand - paid;

                        document.getElementById('isub').innerText = sub.toFixed(2);
                        document.getElementById('isub_val').value = sub;
                        document.getElementById('igrand').innerText = grand.toFixed(2);
                        document.getElementById('igrand_val').value = grand;
                        
                        document.getElementById('idue').innerText = due.toFixed(2);
                        document.getElementById('idue_val').value = due;
                    }

                    function generatePDF(base64Data) {
                        const data = JSON.parse(atob(base64Data));
                        const logo = "<?= $logoSrc ?>";
                        const company = "<?= xss_clean($agency['company_name']) ?>";
                        const address = "<?= xss_clean($agency['address']) ?>";
                        const phone = "<?= xss_clean($agency['company_phone']) ?>";
                        const email = "<?= xss_clean($agency['company_email']) ?>";
                        const CURRENCY_CODE = "<?= $currencyCode ?>";
                        
                        const discountRow = data.discount > 0 ? `
                            <div style="display: flex; justify-content: space-between; margin-bottom: 12px; color:#ef4444;">
                                <span>Discount:</span><span>- ${CURRENCY_CODE} ${data.discount}</span>
                            </div>` : '';
                        
                        const taxRow = data.tax > 0 ? `
                            <div style="display: flex; justify-content: space-between; margin-bottom: 12px; color: #4b5563;">
                                <span>Tax:</span><span>+ ${CURRENCY_CODE} ${data.tax}</span>
                            </div>` : '';

                        const htmlContent = `
                            <div style="padding: 40px; font-family: 'Helvetica', sans-serif; color: #1f2937;">
                                <div style="display: flex; justify-content: space-between; border-bottom: 2px solid #e5e7eb; padding-bottom: 25px; margin-bottom: 30px;">
                                    <div><img src="${logo}" style="max-height: 80px; max-width: 250px; object-fit: contain;"></div>
                                    <div style="text-align: right;">
                                        <h1 style="color: #4f46e5; margin:0; font-size: 36px; font-weight: 800; letter-spacing: 1px;">INVOICE</h1>
                                        <p style="margin:8px 0 0 0; color:#6b7280; font-size: 16px;">#${data.invoice_number}</p>
                                        <p style="margin:4px 0 0 0; color:#6b7280; font-size: 14px;">Issue Date: ${data.issue_date}</p>
                                    </div>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 40px;">
                                    <div>
                                        <p style="font-weight:bold; color: #9ca3af; text-transform: uppercase; font-size: 12px; letter-spacing: 1px; margin-bottom:8px;">Billed From</p>
                                        <p style="margin:0; font-weight: bold; font-size: 18px;">${company}</p>
                                        <p style="margin:4px 0 0 0; font-size:14px; color:#4b5563; max-width: 250px;">${address}</p>
                                        <p style="margin:4px 0 0 0; font-size:14px; color:#4b5563;">Mobile: ${phone}</p>
                                        <p style="margin:4px 0 0 0; font-size:14px; color:#4b5563;">Email: ${email}</p>
                                    </div>
                                    <div style="text-align: right;">
                                        <p style="font-weight:bold; color: #9ca3af; text-transform: uppercase; font-size: 12px; letter-spacing: 1px; margin-bottom:8px;">Billed To</p>
                                        <p style="margin:0; font-weight: bold; font-size: 18px;">${data.customer_name}</p>
                                        <p style="margin:4px 0 0 0; font-size:14px; color:#4b5563;">Mobile: ${data.mobile || 'N/A'}</p>
                                        <p style="margin:4px 0 0 0; font-size:14px; color:#4b5563;">Email: ${data.email || 'N/A'}</p>
                                    </div>
                                </div>
                                <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px; border: 1px solid #e5e7eb;">
                                    <tr style="background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
                                        <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #374151;">Description</th>
                                        <th style="padding: 12px 16px; text-align: center; font-weight: 600; color: #374151;">Qty</th>
                                        <th style="padding: 12px 16px; text-align: right; font-weight: 600; color: #374151;">Price</th>
                                        <th style="padding: 12px 16px; text-align: right; font-weight: 600; color: #374151;">Total</th>
                                    </tr>
                                    <tr style="border-bottom: 1px solid #e5e7eb;">
                                        <td style="padding: 16px;">${data.service_desc}</td>
                                        <td style="padding: 16px; text-align: center;">${data.quantity}</td>
                                        <td style="padding: 16px; text-align: right;">${data.unit_price}</td>
                                        <td style="padding: 16px; text-align: right; font-weight: bold;">${data.subtotal}</td>
                                    </tr>
                                </table>
                                <div style="display: flex; justify-content: flex-end;">
                                    <div style="width: 320px; background: #f9fafb; padding: 20px; border-radius: 8px;">
                                        <div style="display: flex; justify-content: space-between; margin-bottom: 12px; color: #4b5563;">
                                            <span>Subtotal:</span><span>${CURRENCY_CODE} ${data.subtotal}</span>
                                        </div>
                                        ${discountRow}
                                        ${taxRow}
                                        <div style="display: flex; justify-content: space-between; border-top: 1px solid #d1d5db; padding-top: 15px; font-weight: 800; font-size: 16px; color: #111827; margin-top: 5px; margin-bottom: 10px;">
                                            <span>Grand Total:</span><span>${CURRENCY_CODE} ${data.grand_total}</span>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px; color: #10b981; font-weight: bold;">
                                            <span>Amount Paid:</span><span>${CURRENCY_CODE} ${data.paid_amount}</span>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; border-top: 1px solid #d1d5db; padding-top: 10px; font-weight: 800; font-size: 18px; color: #ef4444;">
                                            <span>Total Due:</span><span>${CURRENCY_CODE} ${data.due_amount}</span>
                                        </div>
                                    </div>
                                </div>
                                <div style="margin-top: 60px; text-align: center; color: #9ca3af; font-size: 12px; border-top: 1px solid #e5e7eb; padding-top: 20px;">
                                    Thank you for your business. Generated securely by South Zone SaaS.
                                </div>
                            </div>
                        `;

                        const element = document.createElement('div');
                        element.innerHTML = htmlContent;
                        html2pdf().from(element).set({
                            margin: 0.5,
                            filename: `Invoice_${data.invoice_number}.pdf`,
                            image: { type: 'jpeg', quality: 0.98 },
                            html2canvas: { scale: 2, useCORS: true },
                            jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
                        }).save();
                    }

                    function searchTable() {
                        const filter = document.getElementById("searchInput").value.toLowerCase();
                        const tr = document.getElementById("dataTable").getElementsByTagName("tr");
                        for (let i = 1; i < tr.length; i++) {
                            let visible = false;
                            const tds = tr[i].getElementsByTagName("td");
                            for (let j = 0; j < tds.length - 1; j++) {
                                if (tds[j] && tds[j].innerText.toLowerCase().indexOf(filter) > -1) {
                                    visible = true; break;
                                }
                            }
                            tr[i].style.display = visible ? "" : "none";
                        }
                    }
                </script>

