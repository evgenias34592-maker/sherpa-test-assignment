import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { Router } from '@angular/router';
import { HttpErrorResponse } from '@angular/common/http';
import { AuthService } from '../../core/services/auth.service';

@Component({
  selector: 'app-login',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [ReactiveFormsModule, MatFormFieldModule, MatInputModule, MatButtonModule],
  templateUrl: './login.html',
  styleUrl: './login.scss',
})
export class Login {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  private readonly formBuilder = inject(FormBuilder);

  protected readonly pending = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly form = this.formBuilder.nonNullable.group({
    email: ['admin@example.com', [Validators.required, Validators.email, Validators.maxLength(180)]],
    password: ['', [Validators.required, Validators.maxLength(200)]],
  });

  protected submit(): void {
    this.form.controls.email.setValue(this.form.controls.email.getRawValue().trim());
    if (this.form.invalid || this.pending()) {
      this.form.markAllAsTouched();
      return;
    }
    this.pending.set(true);
    this.error.set(null);
    const { email, password } = this.form.getRawValue();
    this.auth.login(email, password).subscribe({
      next: () => {
        void this.router.navigate(['/products']);
      },
      error: (error: unknown) => {
        this.pending.set(false);
        this.error.set(
          error instanceof HttpErrorResponse
            ? ((error.error as { error?: string } | null)?.error ?? 'Не удалось войти')
            : 'Не удалось войти',
        );
      },
    });
  }

  protected emailError(): string | null {
    return this.controlError(this.form.controls.email, {
      required: 'Укажите email',
      email: 'Некорректный email',
      maxlength: 'Email не длиннее 180 символов',
    });
  }

  protected passwordError(): string | null {
    return this.controlError(this.form.controls.password, {
      required: 'Укажите пароль',
      maxlength: 'Пароль не длиннее 200 символов',
    });
  }

  private controlError(
    control: { touched: boolean; errors: Record<string, unknown> | null },
    messages: Record<string, string>,
  ): string | null {
    if (!control.touched || control.errors === null) {
      return null;
    }
    for (const [key, message] of Object.entries(messages)) {
      if (control.errors[key]) {
        return message;
      }
    }

    return 'Проверьте значение поля';
  }
}
