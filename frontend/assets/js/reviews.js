const S = window.Shop;
const e = S.escape;

export async function mountReviews(root, productId, title = '') {
  // A public GET succeeds anonymously; restore an expired PHP session before reading eligibility.
  try { await window.Auth?.currentUser(); } catch { /* Public reviews remain available. */ }
  let page = 1;
  async function load() {
    try {
      const {data} = await S.api(`reviews.php?product_id=${Number(productId)}&page=${page}`);
      root.innerHTML = `<h2>${title ? 'Đánh giá ' + e(title) : 'Đánh giá từ khách đã mua hàng'}</h2>
        ${S.ratingSummary({id:productId,...data})}
        ${data.can_review ? `<form class="review-form" novalidate><fieldset><legend>Đánh giá của bạn</legend><div class="review-options">${[1,2,3,4,5].map(n => `<label><input type="radio" name="rating" value="${n}" ${data.my_review?.rating === n ? 'checked' : ''}> ${n} ★</label>`).join('')}</div></fieldset><label class="field">Nhận xét<textarea name="feedback" rows="3" maxlength="2000" required>${e(data.my_review?.feedback || '')}</textarea></label><button class="button" type="submit">${data.my_review ? 'Cập nhật đánh giá' : 'Gửi đánh giá'}</button><p class="review-status" role="status"></p></form>` : `<p class="subtle">${data.logged_in ? 'Bạn có thể đánh giá sau khi đơn hàng chứa sản phẩm này đã hoàn thành.' : 'Đăng nhập và hoàn thành đơn mua sản phẩm để viết đánh giá.'}</p>`}
        ${title ? '' : `<div class="review-list">${data.items.map(r => `<article class="review-item"><strong>${e(r.reviewer_name)}</strong> <span class="review-score">${'★'.repeat(r.rating)}${'☆'.repeat(5-r.rating)}</span><small>${e(r.updated_at)}</small><p class="review-feedback">${e(r.feedback)}</p></article>`).join('') || '<p>Chưa có đánh giá.</p>'}</div>${data.total_pages > 1 ? `<nav aria-label="Trang đánh giá"><button type="button" data-review-page="${page-1}" ${page <= 1 ? 'disabled' : ''}>Trước</button> ${page}/${data.total_pages} <button type="button" data-review-page="${page+1}" ${page >= data.total_pages ? 'disabled' : ''}>Sau</button></nav>` : ''}`}`;
      root.querySelectorAll('[data-review-page]').forEach(button => button.addEventListener('click', () => { page = Number(button.dataset.reviewPage); load(); }));
      root.querySelector('form')?.addEventListener('submit', async event => {
        event.preventDefault();
        const form = event.currentTarget, button = form.querySelector('button'), status = form.querySelector('.review-status');
        if (button.disabled) return;
        const fields = new FormData(form), rating = Number(fields.get('rating')), feedback = String(fields.get('feedback') || '').trim();
        if (rating < 1 || rating > 5 || !feedback) { status.textContent = 'Hãy chọn 1–5 sao và nhập nhận xét.'; return; }
        button.disabled = true; status.textContent = 'Đang lưu…';
        try {
          const saved = await S.api('reviews.php', {method:data.my_review ? 'PUT' : 'POST',body:{product_id:Number(productId),rating,feedback}});
          document.querySelectorAll(`[data-product-rating="${Number(productId)}"]`).forEach(el => { el.outerHTML = S.ratingSummary({id:productId,...saved.data}); });
          await load();
          const message = root.querySelector('.review-status'); if (message) message.textContent = 'Đã lưu đánh giá.';
        } catch (error) { status.textContent = error.message; button.disabled = false; }
      });
    } catch (error) { S.error(root, error.message); }
  }
  await load();
}
