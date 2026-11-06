import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { AuthService } from '../core/services/auth.service';
import { TokenStorage } from '../core/token-storage';
import { ImportStatus } from '../shared/import-status';

@Component({
  selector: 'app-shell',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [RouterOutlet, RouterLink, RouterLinkActive, ImportStatus],
  templateUrl: './shell.html',
  styleUrl: './shell.scss',
})
export class Shell {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  private readonly tokens = inject(TokenStorage);

  protected readonly user = this.tokens.user();

  protected logout(): void {
    this.auth.logout();
    void this.router.navigate(['/login']);
  }
}
